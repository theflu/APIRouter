<?php

namespace APIRouter;

use APIRouter\Traits\MessageTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class ServerRequest implements ServerRequestInterface
{
    use MessageTrait;

    private array $attributes = [];
    private string $method;
    private string $protocol;
    private string $request_target;
    private UriInterface $uri;
    private array $server_params = [];
    private array $cookie_params = [];
    private array $query_params = [];
    private $body_parsed;
    private array $uploaded_files = [];

    public function __construct(?string $method = null, $uri = null, $body = null, string $version = '1.1')
    {
        $this->server_params = $_SERVER;
        $this->cookie_params = $_COOKIE;
        $this->uploaded_files = $_FILES;
        $this->protocol = $version;
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];

        if(is_null($uri)) {
            $uri = $this->buildUrl();
        }
        
        if (!($uri instanceof Uri)) {
            $uri = new Uri($uri);
        }
        $this->uri = $uri;
        
        if (is_null($method)) {
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }
        $this->method = $method;

        parse_str($uri->getQuery(), $this->query_params);
    }

    public function getServerParams(): array
    {
        return $this->server_params;
    }

    public function getCookieParams(): array
    {
        return $this->cookie_params;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $request = clone $this;
        $request->cookie_params = $cookies;

        return $request;
    }

    public function getQueryParams(): array
    {
        return $this->query_params;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $request = clone $this;
        $request->query_params = $query;

        return $request;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploaded_files;
    }

    public function withUploadedFiles(array $uploaded_files): ServerRequestInterface
    {
        $request = clone $this;
        $request->uploaded_files = $uploaded_files;

        return $request;
    }

    public function getParsedBody()
    {
        return $this->body_parsed;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if (!is_array($data) && !is_object($data) && null !== $data) {
            throw new \InvalidArgumentException('Parameter MUST be object, array or null');
        }

        $request = clone $this;
        $request->body_parsed = $data;

        return $request;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($attribute, $default = null)
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        if (false === \array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    public function withAttribute($attribute, $value): ServerRequestInterface
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        $request = clone $this;
        $request->attributes[$attribute] = $value;

        return $request;
    }

    public function withoutAttribute($attribute): ServerRequestInterface
    {
        if (!\is_string($attribute)) {
            throw new \InvalidArgumentException('Attribute name must be a string');
        }

        if (false === \array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        $request = clone $this;
        unset($request->attributes[$attribute]);

        return $request;
    }

    public function getRequestTarget(): string
    {
        return $this->request_target;
    }

    public function withRequestTarget(string $request_target): ServerRequestInterface
    {
        $request = clone ($this);
        $request->request_target = $request_target;

        return $request;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): ServerRequestInterface
    {
        $request = clone ($this);
        $request->method = $method;

        return $request;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): ServerRequestInterface
    {
        $request = clone ($this);
        $request->uri = $uri;

        return $request;
    }

    private function buildUrl(bool $forwarded_host = false): string
    {
        $ssl = (!empty($this->server_params['HTTPS']) && $this->server_params['HTTPS'] == 'on');
        $sp = isset($this->server_params['SERVER_PROTOCOL']) ? strtolower($this->server_params['SERVER_PROTOCOL']) : 'http/1.1';
        $protocol = strpos($sp, '/') !== false ? substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '') : 'http';
        $port = isset($this->server_params['SERVER_PORT']) ? $this->server_params['SERVER_PORT'] : '80';
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
        $host = ($forwarded_host && isset($this->server_params['HTTP_X_FORWARDED_HOST'])) ? $this->server_params['HTTP_X_FORWARDED_HOST'] : (isset($this->server_params['HTTP_HOST']) ? $this->server_params['HTTP_HOST'] : null);
        $server_name = isset($this->server_params['SERVER_NAME']) ? $this->server_params['SERVER_NAME'] : 'localhost';
        $host = isset($host) ? $host : $server_name . $port;
        $request_uri = isset($this->server_params['REQUEST_URI']) ? $this->server_params['REQUEST_URI'] : '/';

        return $protocol . '://' . $host . $request_uri;
    }
}