<?php

declare(strict_types=1);

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\HealthCheckController;
use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\UserController;
use App\Presentation\Middleware\ApiRateLimitMiddleware;
use App\Presentation\Middleware\AuthMiddleware;
use Erebor\Mithril\Router;

return function (Router $router): void {

    $router->post('/api/login', [AuthController::class, 'login']);
    $router->post('/api/register', [AuthController::class, 'register']);

    $router->get('/api/health', [HealthCheckController::class, 'check']);

    $router->get('/api/users', [UserController::class, 'index'], [
        AuthMiddleware::class,
    ]);

    $router->get('/api/products', [ProductController::class, 'index']);
    $router->get('/api/products/secure', [ProductController::class, 'index'], [
        AuthMiddleware::class,
    ]);
    $router->get('/api/products/limited', [ProductController::class, 'index'], [
        ApiRateLimitMiddleware::class,
    ]);
};
