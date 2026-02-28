<?php

namespace APIRouter\Http;

class Response
{
    private int $status_code = 200;
    private array $headers = [];
    private $content;

    public function setStatusCode(int $code): self
    {
        $this->status_code = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setJson($data): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->content = json_encode($data);
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status_code);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->content;
    }
}
