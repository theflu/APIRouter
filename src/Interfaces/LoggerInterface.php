<?php

namespace APIRouter\Interfaces;

use APIRouter\Http\Request;
use APIRouter\Http\Response;

interface LoggerInterface
{
    /**
     * Log an API request and response.
     *
     * @param Request $request
     * @param Response $response
     * @param float $latency Latency in seconds (or milliseconds, implementation detail)
     * @param mixed|null $user The authenticated user context, if any
     * @return void
     */
    public function logRequest(Request $request, Response $response, float $latency, $user = null): void;
}
