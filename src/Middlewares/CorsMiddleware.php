<?php

namespace APIRouter\Middlewares;

use APIRouter\Interfaces\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowed_origins List of origins to allow, or ['*'] to allow all
     * @param string[] $allowed_headers Explicit header list, or [] to reflect Access-Control-Request-Headers
     */
    public function __construct(
        private array $allowed_origins,
        private array $allowed_headers = [],
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Preflight — add Allow-Headers
        if (\strtoupper($request->getMethod()) === 'OPTIONS') {
            $requested_headers = $request->getHeaderLine('Access-Control-Request-Headers');
            if ($requested_headers) {
                $allow_headers = $this->allowed_headers
                    ? \implode(', ', $this->allowed_headers)
                    : $requested_headers; // reflect
                $response = $response->withHeader('Access-Control-Allow-Headers', $allow_headers);
            }
        }

        // Wildcard — allow all origins, set header to * (no Vary needed)
        if (\in_array('*', $this->allowed_origins)) {
            return $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        // Specific origins — echo back the matched origin with Vary
        $request_origin = $request->getHeaderLine('Origin');
        if ($request_origin && \in_array($request_origin, $this->allowed_origins)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $request_origin)
                ->withAddedHeader('Vary', 'Origin');
        }

        return $response;
    }
}
