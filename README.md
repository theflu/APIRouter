# APIRouter

A lightweight PHP API router with built-in support for authentication, authorization, logging, route groups, and rate limiting.

## Features

- **Request Type** - `GET`, `POST`, `PUT`, `DELETE` with parameter extraction
- **URI Variables** - `/user/{uid}`
- **Route groups** - Nest routes under a shared prefix
- **Authentication & Authorization** - Plug in your own via interfaces
- **Logging** - Per-request latency and user context

## Installation

```bash
composer require theflu/APIRouter
```

Or clone and autoload via PSR-4:

```json
{
    "autoload": {
        "psr-4": {
            "APIRouter\\": "src/"
        }
    }
}
```

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use APIRouter\Router;
use APIRouter\Http\Request;
use APIRouter\Http\Response;

$router = new Router();

$router->get('/hello', function (Request $req, Response $res) {
    $res->setJson(['message' => 'Hello, World!']);
});

$router->dispatch();
```

## Route Groups

Group routes under a shared prefix. Groups can be nested.

```php
// Registers: GET /api/v1/users, GET /api/v1/admin/stats
$router->group('/api/v1', function (Router $router) {

    $router->get('/users', function (Request $req, Response $res) {
        $res->setJson(['users' => []]);
    });

    $router->group('/admin', function (Router $router) {
        $router->get('/stats', function (Request $req, Response $res) {
            $res->setJson(['total' => 42]);
        });
    });
});
```

## Route Parameters

Use `{param}` syntax. Parameters are passed as the third argument to handlers.

```php
$router->get('/users/{id}', function (Request $req, Response $res, array $params) {
    $res->setJson(['id' => $params['id']]);
});
```

## Authentication & Authorization

Implement the provided interfaces and pass them to the Router constructor:

```php
use APIRouter\Interfaces\AuthenticatorInterface;
use APIRouter\Interfaces\PermissionsInterface;

class MyAuth implements AuthenticatorInterface {
    public function authenticate(Request $request) {
        // Return user object/array or null
    }
}

class MyAuthz implements PermissionsInterface {
    public function hasPermission($user, string $permission): bool {
        // Check user permissions
    }
}

$router = new Router(null, new MyAuth(), new MyAuthz());

$router->get('/admin', $handler)->requirePermission('admin_access');
```

## Logging

Implement `LoggerInterface` to log every request with latency and user context:

```php
use APIRouter\Interfaces\LoggerInterface;

class MyLogger implements LoggerInterface {
    public function logRequest(Request $request, Response $response, float $latency, $user = null): void {
        // Log request details
    }
}

$router = new Router(new MyLogger());
```
