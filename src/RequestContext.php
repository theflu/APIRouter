<?php

namespace APIRouter;

class RequestContext
{
    private array $context = [];

    public function getContext(string $name, $default = null):mixed
    {
        $context_value = $default;
        if (key_exists($name, $this->context)) {
            $context_value = $this->context[$name];
        }

        return $context_value;
    }

    public function getContexts():array
    {
        return $this->context;
    }

    public function hasContext(string $name):bool
    {
        return key_exists($name, $this->context);
    }

    public function withContext(string $name, $value):void
    {
        $this->context[$name] = $value;
    }
}
