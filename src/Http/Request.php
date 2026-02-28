<?php

namespace APIRouter\Http;

class Request
{
    private array $server;
    private array $query_params;
    private array $body;
    private array $headers;
    private array $attributes = [];
    private ?string $raw_body = null;
    private ?array $parsed_json = null;
    private bool $body_parsed = false;

    public function __construct(array $server = [], array $query_params = [], array $body = [], array $headers = [])
    {
        $this->server = $server ?: $_SERVER;
        $this->query_params = $query_params ?: $_GET;
        $this->body = $body ?: $_POST;
        $this->headers = $headers ?: $this->getAllHeaders();
    }

    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($this->server as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace([' ', '_'], '-', ucwords(strtolower(substr($name, 5)), ' _-'))] = $value;
            } else {
                $headers[str_replace([' ', '_'], '-', ucwords(strtolower($name), ' _-'))] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        // Remove query string
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return rawurldecode($uri);
    }

    /**
     * Get a header value by name.
     */
    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Get a GET param by key.
     */
    public function getParam(string $key, $default = null)
    {
        return $this->query_params[$key] ?? $default;
    }

    /**
     * Get a single parameter from the request body.
     */
    public function getBodyParam(string $key, $default = null)
    {
        return $this->getBody()[$key] ?? $default;
    }

    /**
     * Get the full parsed request body as an associative array.
     * For JSON requests, parses the raw body once and caches the result.
     * For form submissions, returns the POST data.
     */
    public function getBody(): array
    {
        if (!$this->body_parsed) {
            $this->body_parsed = true;

            if ($this->isJson()) {
                $raw = $this->getRawBody();
                $this->parsed_json = json_decode($raw, true) ?: [];
            }
        }

        return $this->parsed_json ?? $this->body;
    }

    /**
     * Get the raw request body string. Read once and cached.
     */
    public function getRawBody(): string
    {
        if ($this->raw_body === null) {
            $this->raw_body = file_get_contents('php://input') ?: '';
        }
        return $this->raw_body;
    }

    public function isJson(): bool
    {
        $content_type = $this->getHeader('Content-Type');
        return $content_type && stripos($content_type, 'application/json') !== false;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }
}
