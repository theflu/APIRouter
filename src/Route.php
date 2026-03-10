<?php

namespace APIRouter;

use APIRouter\Interfaces\MiddlewareInterface;
use APIRouter\Interfaces\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Route
{
    private string $method;
    private string $path;
    private mixed $handler;
    private array $params = [];
    private array $middlewares = [];
    private ?string $required_permission = null;
    private bool $requires_auth = false;

    public function __construct(string $method, string $path, RequestHandlerInterface|callable $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->handler instanceof RequestHandlerInterface) {
            return $this->handler->handle($request);
        }

        return call_user_func($this->handler, $request);
    }

    public function getMethod(): string
    {
        return $this->method;
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
