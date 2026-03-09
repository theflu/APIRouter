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
    private $stream  = null;

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
        return $this->headers;
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
        $message = clone ($this);

        // If the header doesn't already exist just create it
        if (!$message->hasHeader($name)) {
            return $message->withHeader($name, $value);
        }

        // Convert string to array
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        // Merge old with new and set/return it
        return $message->withHeader($name, array_merge($message->headers[$key], $value));
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
     * @return static
     */
    
    public function withJsonBody($content): MessageInterface
    {
        if (!is_array($content) && !is_object($content)) {
            throw new InvalidArgumentException(sprintf('Expected array or object, but got %s', gettype($content)));
        }

        $josn  = json_encode($content);

        // TODO check for errors after json encode

        $message = clone ($this);

        // JSON content type if not already aaplied
        if (!in_array('application/json', $this->getHeader('Content-Type'))) {
            $message = $this->withAddedHeader('Content-Type', 'application/json');
        }
        $message->stream = Stream::create($josn);

        return $message;
    }
}