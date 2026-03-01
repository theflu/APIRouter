<?php

namespace APIRouter;

class Route
{
    private string $method;
    private string $path;
    private $handler;
    private array $params = [];
    private array $pre_middlewares = [];
    private array $post_middlewares = [];
    private ?string $required_permission = null;
    private bool $requires_auth = false;

    public function __construct(string $method, string $path, callable $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): callable
    {
        return function ($req, $res, $params, $next) {
            call_user_func($this->handler, $req, $res, $params);
            return $next($req, $res, $next);
        };
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function addPreMiddleware(callable $middleware): self
    {
        $this->pre_middlewares[] = $middleware;
        return $this;
    }

    public function getPreMiddlewares(): array
    {
        return $this->pre_middlewares;
    }

    public function addPostMiddleware(callable $middleware): self
    {
        $this->post_middlewares[] = $middleware;
        return $this;
    }

    public function getPostMiddlewares(): array
    {
        return $this->post_middlewares;
    }

    public function requireAuth(): self
    {
        $this->requires_auth = true;
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
