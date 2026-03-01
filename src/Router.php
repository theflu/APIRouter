<?php

namespace APIRouter;

use APIRouter\Http\Request;
use APIRouter\Http\Response;
use APIRouter\Interfaces\LoggerInterface;
use APIRouter\Interfaces\AuthenticatorInterface;
use APIRouter\Interfaces\PermissionsInterface;

class Router
{
    private bool $debug_enabled = false;
    private array $routes = [];
    private array $global_pre_auth_middlewares = [];
    private array $global_pre_route_middlewares = [];
    private array $global_post_route_middlewares = [];
    private array $prefix_stack = [];

    /** @var mixed */
    private $user = null;
    private ?LoggerInterface $logger = null;
    private ?AuthenticatorInterface $authenticator = null;
    private ?PermissionsInterface $authorizer = null;

    public const PRE_AUTH = 0;
    public const PRE_ROUTE = 1;
    public const POST_ROUTE = 2;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?AuthenticatorInterface $authenticator = null,
        ?PermissionsInterface $authorizer = null
    ) {
        $this->logger = $logger;
        $this->authenticator = $authenticator;
        $this->authorizer = $authorizer;
    }

    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, $handler): Route
    {
        $prefix = implode('', $this->prefix_stack);
        $route = new Route($method, $prefix . $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Define a group of routes that share a common prefix.
     * Groups can be nested.
     *
     * @param string $prefix The URL prefix for all routes in the group
     * @param callable $callback Receives the Router instance to define routes on
     */
    public function group(string $prefix, callable $callback): void
    {
        $this->prefix_stack[] = rtrim($prefix, '/');
        $callback($this);
        array_pop($this->prefix_stack);
    }

    public function use(callable $middleware, int $position = self::PRE_ROUTE): void
    {
        switch ($position) {
            case self::PRE_AUTH:
                $this->global_pre_auth_middlewares[] = $middleware;
                break;
            case self::PRE_ROUTE:
                $this->global_pre_route_middlewares[] = $middleware;
                break;
            case self::POST_ROUTE:
                $this->global_post_route_middlewares[] = $middleware;
                break;
            default:
                throw new \InvalidArgumentException('Invalid postion value');

        }
    }

    public function debug(bool $enable): void
    {
        $this->debug_enabled = $enable;
    }

    public function dispatch(?Request $request = null): void
    {
        $request = $request ?? new Request();
        $start_time = microtime(true);
        $response = new Response();
        $this->user = null;

        try {
            // Build stack
            $route_stack = function ($req, $res) {
                $this->authenticate($req);
                $this->route($req, $res);
            };

            // Add pre auth middleware
            foreach (array_reverse($this->global_pre_auth_middlewares) as $middleware) {
                $next = $route_stack;

                $route_stack = function ($req, $res) use ($middleware, $next) {
                    call_user_func($middleware, $req, $res, $next);
                };
            }

            // Run route stack
            $route_stack($request, $response);


        } catch (\LogicException $e) {
            // Developer configuration errors should still surface clearly
            throw $e;
        } catch (\Throwable $e) {
            $error = ['error' => 'Internal Server Error'];

            if ($this->debug_enabled) {
                $error['debug'] = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ];
            }

            $response->setStatusCode(500)->setJson($error);
        }

        $response->send();

        // Logging
        if ($this->logger) {
            $latency = microtime(true) - $start_time;
            $this->logger->logRequest($request, $response, $latency, $this->user);
        }
    }

    private function authenticate(Request $request): void
    {
        if ($this->authenticator) {
            $this->user = $this->authenticator->authenticate($request);
            if ($this->user) {
                $request->setAttribute('user', $this->user);
            }
        }
    }

    private function route(Request $request, Response $response): void
    {
        $route = $this->match($request);

        if (!$route) {
            $response->setStatusCode(404)->setJson(['error' => 'Not Found']);
        } elseif ($route->isAuthRequired() && !$this->authenticator) {
            throw new \LogicException(
                "Route '{$route->getPath()}' requires authentication but no AuthenticatorInterface was provided."
            );
        } elseif ($route->getRequiredPermission() && !$this->authorizer) {
            throw new \LogicException(
                "Route '{$route->getPath()}' requires permission '{$route->getRequiredPermission()}' but no PermissionsInterface was provided."
            );
        } elseif ($route->isAuthRequired() && !$this->user) {
            // Route requires authentication but no authenticated user
            $response->setStatusCode(401)->setJson(['error' => 'Unauthorized']);
        } elseif (
            $route->getRequiredPermission()
            && !$this->authorizer->hasPermission($this->user, $route->getRequiredPermission())
        ) {
            // User lacks the required permission
            $response->setStatusCode(403)->setJson(['error' => 'Forbidden']);
        } else {
            // Execute middlewares and handler
            $this->executeRoute($route, $request, $response, $this->user);
        }
    }

    private function match(Request $request): ?Route
    {
        $uri = $request->getUri();
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            if ($route->getMethod() !== $method) {
                continue;
            }

            $quoted_path = preg_quote($route->getPath(), '#');
            $pattern = preg_replace('/\\\\\{([a-zA-Z0-9_]+)\\\\\}/', '(?P<$1>[^/]+)', $quoted_path);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $uri, $matches)) {
                // Filter numeric keys
                $params = array_filter($matches, '\is_string', ARRAY_FILTER_USE_KEY);
                $route->setParams($params);
                return $route;
            }
        }

        return null;
    }

    private function executeRoute(Route $route, Request $request, Response $response, $user)
    {
        // Stack  pre route middlewares: Global -> Route Specific -> Handler
        $pre_middlewares = array_merge($this->global_pre_route_middlewares, $route->getPreMiddlewares());

        // Stack  post route middlewares: Handler -> Global -> Route Specific
        $post_middlewares = array_merge($this->global_post_route_middlewares, $route->getPostMiddlewares());

        // Get the handler from the route
        $handler = $route->getHandler();

        // Build the post middleware stack
        $post_dispatch = function ($req, $res) {};
        foreach (array_reverse($post_middlewares, true) as $key => $middleware) {
            $next = $post_dispatch;

            $post_dispatch = function ($req, $res) use ($middleware, $next) {
                return call_user_func($middleware, $req, $res, $next);
            };
        }

        // Add the route handler to the stack
        $dispatch = function ($req, $res) use ($handler, $route, $post_dispatch) {
            return call_user_func($handler, $req, $res, $route->getParams(), $post_dispatch);
        };

        // Add the pre middle ware tot eh stack
        foreach (array_reverse($pre_middlewares) as $middleware) {
            $next = $dispatch;
            $dispatch = function ($req, $res) use ($middleware, $next) {
                return call_user_func($middleware, $req, $res, $next);
            };
        }

        // Run the stack
        return $dispatch($request, $response);
    }
}
