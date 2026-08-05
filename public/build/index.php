<?php

declare(strict_types=1);

// Fast path: health check sem bootstrap (máxima performance para load balancers / k8s)
// $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// if ($method === 'GET' && $path === '/api/health') {
//     header('Content-Type: application/json');
//     header('Cache-Control: no-cache');
//     echo json_encode([
//         'status' => 'ok',
//         'timestamp' => time(),
//         'service' => 'Durin\'s Forge API',
//         'version' => '1.0.2',
//     ]);
//     return;
// }

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Kernel;
use Erebor\Mithril\Http\Request;

$kernel = new Kernel();
$kernel->boot();

$request = Request::createFromGlobals();

$response = $kernel->handle($request);

$response->send();
