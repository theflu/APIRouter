<?php

namespace APIRouter;

class Route
{
    private string $method;
    private string $path;
    private $handler;
    private array $params = [];
    private ?string $required_permission = null;
    private bool $requires_auth = false;

    public function __construct(string $method, string $path, $handler)
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

    public function getHandler()
    {
        return $this->handler;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
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
