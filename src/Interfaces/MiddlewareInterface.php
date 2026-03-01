<?php

namespace APIRouter\Interfaces;

use APIRouter\Http\Request;
use APIRouter\Http\Response;

interface MiddlewareInterface
{
    /**
     * Invoke the middleware
     *
     * @param Request $request
     * @param Response $response
     * @param callable $next Next middleware or handler in the chain
     */
    
    public function __invoke(Request $request, Response $response, callable $next);
}
