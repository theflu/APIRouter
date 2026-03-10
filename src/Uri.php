<?php

namespace APIRouter;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{

    private string $scheme = '';
    private string $user_info = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

    private const STD_SCHEMES = [
        'http' => 80,
        'https' => 443
    ];

    public function __construct(string $uri)
    {
        $parts = parse_url($uri);
        
        $this->scheme = key_exists('scheme', $parts) ? strtolower($parts['scheme']) : '';
        $this->user_info = key_exists('user', $parts) ? $parts['user'] : '';
        $this->user_info .= key_exists('password', $parts) ? ':' . $parts['user'] : '';
        $this->host = key_exists('host', $parts) ? $parts['host'] : '';
        $this->path = key_exists('path', $parts) ? $parts['path'] : '';
        $this->fragment = key_exists('fragment', $parts) ? $parts['fragment'] : '';

        if (key_exists('port', $parts)) {
            $this->port = (int) $parts['port'];
        }
    }

    public function getAuthority(): string
    {
        if (empty($this->host)) {
            return '';
        }

        $authority = $this->host;
        if (!empty($this->user_info)) {
            $authority = $this->user_info . '@' . $authority;
        }

        if (isset($this->port) && !in_array($this->port, self::STD_SCHEMES)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $scheme = strtolower($scheme);

        // Check for valid scheme
        if (!key_exists($scheme, self::STD_SCHEMES)) {
            throw new \InvalidArgumentException(\sprintf('Only HTTP/HTTPS is allowed. %s was given', $scheme));
        }

        $uri = clone ($this);
        $uri->scheme = $scheme;

        return $uri;
    }

    public function getUserInfo(): string
    {
        return $this->user_info;
    }

    public function withUserInfo(string $user, string|null $password = null): UriInterface
    {
        $uri = clone ($this);
        $uri->user_info = $user;

        if (!is_null($password)) {
            $uri->user_info .= ':' . $password;
        }

        return $uri;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function withHost(string $host): UriInterface
    {
        $uri = clone ($this);
        $uri->host = $host;

        return $uri;
    }

    public function getPort(): int|null
    {
        return $this->port;
    }

    public function withPort(int|null $port): UriInterface
    {
        $uri = clone ($this);
        $uri->port = $port;

        return $uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function withPath(string $path): UriInterface
    {
        $uri = clone ($this);
        $uri->path = $path;

        return $uri;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function withQuery(string $query): UriInterface
    {
        $uri = clone ($this);
        $uri->query = $query;

        return $uri;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $uri = clone ($this);
        $uri->fragment = $fragment;

        return $uri;
    }

    public function __tostring(): string
    {
        $uri = '';
        if (!empty($this->scheme)) {
            $uri = $this->scheme . '://';
        }

        $uri .= $this->getAuthority() . $this->path;

        if (!empty($this->query)) {
            $uri .= '?' . $this->query;
        }

        if (!empty($this->fragment)) {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }
}