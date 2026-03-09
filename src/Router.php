<?php

namespace APIRouter;


use APIRouter\Interfaces\MiddlewareInterface;
use APIRouter\Interfaces\RequestHandlerInterface;

class Router
{
    private bool $debug_enabled = false;
    private array $routes = [];
    private array $middlewares = [];
    private array $prefix_stack = [];

    public function get(string $path, RequestHandlerInterface|callable $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, RequestHandlerInterface|callable $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, RequestHandlerInterface|callable $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, RequestHandlerInterface|callable $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, RequestHandlerInterface|callable $handler): Route
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

    public function use(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function debug(bool $enable): void
    {
        $this->debug_enabled = $enable;
    }

    public function loadRoutes(string $routes_path): void
    {
        if (is_dir($routes_path)) {
            $di = new \RecursiveDirectoryIterator($routes_path);
            foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
                if ($file->isFile())
                    require $filename;
            }
        } elseif (is_file($routes_path)) {
            require $routes_path;
        } else {
            throw new \Exception('PAth does not exist');
        }
    }

    public function dispatch(?ServerRequest $request = null): void
    {
        $start_time = microtime(true);

        // Create the request if we didn't get one
        if (is_null($request)) {
            $request = new ServerRequest();
        }

        // Add the start_time attribute
        $request = $request->withAttribute('start_time', $start_time);

        // Find the matching route
        $route = $this->match($request);

        if ($route === null) {
            $this->emit(new Response(404));
            return;
        }

        try {
            $middlewares = array_merge($this->middlewares, $route->getMiddlewares());

            $runner = new class ($middlewares, $route) implements \Psr\Http\Server\RequestHandlerInterface {
                private array $middlewares;
                private $route;
                private int $index = 0;

                public function __construct(array $middlewares, Route $route)
                {
                    $this->middlewares = $middlewares;
                    $this->route = $route;
                }

                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    if (isset($this->middlewares[$this->index])) {
                        $middleware = $this->middlewares[$this->index];
                        $this->index++;
                        return $middleware->process($request, $this);
                    }
                    return $this->route->handle($request);
                }
            };

            $response = $runner->handle($request);
        } catch (\LogicException $e) {
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

            $response = (new Response(500, ['Content-Type: application/json']))->withJsonBody($error);
        }

        $this->emit($response);
    }

    private function match(ServerRequest $request): ?Route
    {
        $uri = $request->getUri()->getPath();
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

    private function emit(Response $response)
    {
        $status_code = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            assert(is_string($header));
            $name = ucwords($header, '-');
            $replace = $name !== 'Set-Cookie';
            foreach ($values as $value) {
                header($name . ': ' . $value, $replace, $status_code);
                $replace = false;
            }
        }

        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $status_code,
            $response->getReasonPhrase() ? ' ' . $response->getReasonPhrase() : ''
        ), true, $status_code);

        echo $response->getBody();
    }
}
