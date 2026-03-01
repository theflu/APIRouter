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
    private array $prefix_stack = [];
    private ?LoggerInterface $logger = null;
    private ?AuthenticatorInterface $authenticator = null;
    private ?PermissionsInterface $authorizer = null;

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

    public function debug(bool $enable): void
    {
        $this->debug_enabled = $enable;
    }

    public function dispatch(?Request $request = null): void
    {
        $request = $request ?? new Request();
        $start_time = microtime(true);
        $response = new Response();
        $user = null;

        try {
            // Authentication
            if ($this->authenticator) {
                $user = $this->authenticator->authenticate($request);
                if ($user) {
                    $request->setAttribute('user', $user);
                }
            }

            // Find matching route
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
            } elseif ($route->isAuthRequired() && !$user) {
                // Route requires authentication but no authenticated user
                $response->setStatusCode(401)->setJson(['error' => 'Unauthorized']);
            } elseif (
                $route->getRequiredPermission()
                && !$this->authorizer->hasPermission($user, $route->getRequiredPermission())
            ) {
                // User lacks the required permission
                $response->setStatusCode(403)->setJson(['error' => 'Forbidden']);
            } else {
                // Execute route
                $this->executeRoute($route, $request, $response, $user);
            }

        } catch (\LogicException $e) {
            // Developer configuration errors should still surface clearly
            throw $e;
        } catch (\Throwable $e) {
            $error = ['error' => 'Internal Server Error'];

            if ($this->debug_enabled) {
                $error['debug'] = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
            }

            $response->setStatusCode(500)->setJson($error);
        }

        $response->send();

        // Logging
        if ($this->logger) {
            $latency = microtime(true) - $start_time;
            $this->logger->logRequest($request, $response, $latency, $user);
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
        // Get the routes handler
        $handler = $route->getHandler();

        // Ensure handler is executable
        if (!is_callable($handler)) {
            throw new \RuntimeException("Route handler is not callable.");
        }

        return call_user_func($handler, $request, $response, $route->getParams());
    }
}
