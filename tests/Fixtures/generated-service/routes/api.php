<?php

declare(strict_types=1);

use EreborCodeForge\Durin\Forge\Core\Http\Controllers\HealthCheckController;
use Erebor\Mithril\Router;

return function (Router $router): void {
    $router->get('/api/health', [HealthCheckController::class, 'check']);
};
