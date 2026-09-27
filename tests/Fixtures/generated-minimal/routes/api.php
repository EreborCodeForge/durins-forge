<?php

declare(strict_types=1);

/** @var \Erebor\Mithril\Router $router */

$router->get('/api/health', static fn () => ['status' => 'ok']);
