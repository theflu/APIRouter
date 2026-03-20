<?php

namespace APIRouter\Traits;

use InvalidArgumentException;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

trait MessageTrait
{
    private array $headers = [];
    private string $version;
    private $stream = null;

    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        $message = clone ($this);
        $message->version = $version;

        return $message;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        $key = strtolower($name);
        return key_exists($key, $this->headers);
    }

    public function getHeader(string $name): array
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        $key = strtolower($name);
        return $this->headers[$key];
    }

    public function getHeaderLine(string $name): string
    {
        $value = '';
        $key = strtolower($name);
        if (key_exists($key, $this->headers)) {
            $value = implode(', ', $this->headers[$key]);
        }

        return $value;
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        $key = strtolower($name);
        $message = clone ($this);

        // Create header if needed
        if (!$message->hasHeader($name)) {
            $message->headers[$key] = [];
        }

        // Convert string to array
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        $message->headers[$key] = $value;

        return $message;
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $key = strtolower($name);

        // If the header doesn't already exist just create it
        if (!$this->hasHeader($name)) {
            return $this->withHeader($name, $value);
        }

        // Convert string to array
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        // Merge old with new and set/return it
        return $this->withHeader($name, array_merge($this->headers[$key], $value));
    }

    public function withoutHeader(string $name): MessageInterface
    {
        $key = strtolower($name);
        $message = clone ($this);

        if (key_exists($key, $message->headers)) {
            unset($message->headers[$key]);
        }

        return $message;
    }

    public function getBody(): StreamInterface
    {
        if (null === $this->stream) {
            $this->stream = Stream::create('');
        }
        return $this->stream;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        $message = clone ($this);
        $message->stream = $body;

        return $message;
    }

    /**
     * Encodes $content in JSON and applies it to boday adding the json content type
     *
     * @param array|object $content
     * @param bool $sub_nvl_utf8 Substitute invalid UTF8 when converting to JSON
     * @return static
     */

    public function withJsonBody($content, bool $sub_nvl_utf8 = false): MessageInterface
    {
        if (!is_array($content) && !is_object($content)) {
            throw new InvalidArgumentException(sprintf('Expected array or object, but got %s', gettype($content)));
        }

        if ($sub_nvl_utf8) {
            $json = json_encode($content, JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            $json = json_encode($content);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(sprintf('Error converting to json: %s', json_last_error_msg()));
        }

        $message = clone ($this);

        // JSON content type if not already aaplied
        if (!in_array('application/json', $this->getHeader('Content-Type'))) {
            $message = $message->withAddedHeader('Content-Type', 'application/json');
        }
        $message->stream = Stream::create($json);

        return $message;
    }
}