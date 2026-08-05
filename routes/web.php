<?php

declare(strict_types=1);

use App\Presentation\Controllers\DashboardController;
use App\Presentation\Controllers\HomeController;
use App\Presentation\Controllers\ExampleController;
use App\Presentation\Middleware\AuthMiddleware;
use Erebor\Mithril\Router;

return function (Router $router): void {

    $router->get('/', [HomeController::class, 'index']);
    $router->get('/example', [ExampleController::class, 'index']);

    $router->get('/login', [DashboardController::class, 'login']);
    $router->get('/dashboard', [DashboardController::class, 'index'], [
        AuthMiddleware::class,
    ]);

};
