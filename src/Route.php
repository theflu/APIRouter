<?php

namespace APIRouter;

use Exception;
use APIRouter\Interfaces\MiddlewareInterface;
use APIRouter\Interfaces\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    private array $methods;
    private string $path;
    private mixed $handler;
    private array $params = [];
    private array $middlewares = [];
    private ?string $required_permission = null;
    private bool $requires_auth = false;

    public function __construct(array $methods, string $path, RequestHandlerInterface|callable|array|string $handler)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->handler instanceof RequestHandlerInterface) {
            return $this->handler->handle($request);
        }

        if (is_array($this->handler)) {
            $class = $this->handler[0];
            $method = $this->handler[1];
            if (is_string($this->handler[0])) {
                return (new $class())->$method($request);
            }

            return $class->$method($request);
        }

        if (is_callable($this->handler)) {
            return call_user_func($this->handler, $request);
        }

        throw new Exception('Invalid route handler');
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function requireAuth(bool $auth = true): self
    {
        $this->requires_auth = $auth;
        return $this;
    }

    public function isAuthRequired(): bool
    {
        return $this->requires_auth || $this->required_permission !== null;
    }

    public function requirePermission(string $permission): self
    {
        $this->required_permission = $permission;
        return $this;
    }

    public function getRequiredPermission(): ?string
    {
        return $this->required_permission;
    }
}
