<?php

declare(strict_types=1);

use App\Presentation\Controllers\AuthController;
use App\Presentation\Controllers\BenchmarkController;
use App\Presentation\Controllers\CheckoutController;
use App\Presentation\Controllers\HealthCheckController;
use App\Presentation\Controllers\JobController;
use App\Presentation\Controllers\PaymentController;
use App\Presentation\Controllers\ProductController;
use App\Presentation\Controllers\ReportController;
use App\Presentation\Controllers\UserController;
use App\Presentation\Middleware\ApiRateLimitMiddleware;
use App\Presentation\Middleware\AuthMiddleware;
use App\Presentation\Middleware\ResponseCacheMiddleware;
use Erebor\Mithril\Router;

return function (Router $router): void {

    $router->post('/api/login', [AuthController::class, 'login']);
    $router->post('/api/register', [AuthController::class, 'register']);

    $router->get('/api/health', [HealthCheckController::class, 'check']);

    $router->get('/api/users', [UserController::class, 'index'], [
        AuthMiddleware::class,
    ]);

    $router->get('/api/products', [ProductController::class, 'index'], [
        ResponseCacheMiddleware::class,
    ]);

    $router->get('/api/products/secure', [ProductController::class, 'secure'], [
        AuthMiddleware::class,
        ResponseCacheMiddleware::class,
    ]);

    $router->get('/api/products/secure2', [ProductController::class, 'secure'], [
        AuthMiddleware::class,
    ]);

    $router->get('/api/products/limited', [ProductController::class, 'index'], [
        ApiRateLimitMiddleware::class,
    ]);

    // --- Heavy I/O simulations (SQLite + sleep); no response cache ---

    $router->post('/api/payments/simulate', [PaymentController::class, 'simulate'], [
        AuthMiddleware::class,
    ]);
    $router->post('/api/payments/authorize', [PaymentController::class, 'authorize'], [
        AuthMiddleware::class,
    ]);

    $router->post('/api/checkout/simulate', [CheckoutController::class, 'simulate'], [
        AuthMiddleware::class,
    ]);

    // Public on purpose — easy wrk contrast vs cached GET /products
    $router->get('/api/reports/slow', [ReportController::class, 'slow']);

    // Mazarbul API pura / hot / diffs — no cache HTTP
    $router->get('/api/benchmark/data', [BenchmarkController::class, 'data']);
    $router->get('/api/benchmark/data-hot', [BenchmarkController::class, 'hot']);
    $router->get('/api/benchmark/data-baseline', [BenchmarkController::class, 'baseline']);
    $router->get('/api/benchmark/diff', [BenchmarkController::class, 'diff']);
    $router->get('/api/benchmark/diff-sql', [BenchmarkController::class, 'diffSql']);

    $router->post('/api/jobs/enqueue', [JobController::class, 'enqueue'], [
        AuthMiddleware::class,
    ]);
    $router->post('/api/jobs/process-next', [JobController::class, 'processNext'], [
        AuthMiddleware::class,
    ]);
    $router->get('/api/jobs/{id}', [JobController::class, 'show'], [
        AuthMiddleware::class,
    ]);
};
