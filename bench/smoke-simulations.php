<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Core/helpers.php';

use App\Core\Http\HttpDispatcher;
use App\Kernel;
use Erebor\Mithril\Http\Request;

$kernel = new Kernel();
$kernel->boot();
$dispatcher = $kernel->getContainer()->get(HttpDispatcher::class);

$token = 'demo-token';

$dispatch = static function (
    string $method,
    string $path,
    array $headers = [],
    string $rawBody = ''
) use ($dispatcher): void {
    $body = $rawBody !== '' ? (json_decode($rawBody, true) ?: []) : [];
    $request = Request::create($method, $path, $headers, $rawBody, body: $body);
    $response = $dispatcher->dispatch($request);
    $snippet = substr($response->getBodyBytes(), 0, 160);
    echo sprintf("%s %s -> %d %s\n", $method, $path, $response->getStatusCode(), $snippet);
};

$auth = [
    'Authorization' => 'Bearer ' . $token,
    'Content-Type' => 'application/json',
];

$dispatch('POST', '/api/payments/simulate', $auth, '{"delay_ms":20,"amount":10}');
$dispatch('POST', '/api/payments/authorize', $auth + ['Idempotency-Key' => 'idem-1'], '{"delay_ms":10,"amount":20}');
$dispatch('POST', '/api/payments/authorize', $auth + ['Idempotency-Key' => 'idem-1'], '{"delay_ms":10,"amount":20}');
$dispatch('POST', '/api/checkout/simulate', $auth, '{"delay_ms":15,"amount":50}');
$dispatch('GET', '/api/reports/slow?delay_ms=5&queries=2');
$dispatch('POST', '/api/jobs/enqueue', $auth, '{"delay_ms":10,"type":"demo"}');

// Capture job id from enqueue for show — re-enqueue and parse via second call
$request = Request::create('POST', '/api/jobs/enqueue', $auth, '{"delay_ms":10}', body: ['delay_ms' => 10]);
$response = $dispatcher->dispatch($request);
$jobId = (int) (json_decode($response->getBodyBytes(), true)['job']['id'] ?? 0);
echo "enqueue-for-show -> {$response->getStatusCode()} id={$jobId}\n";
$dispatch('GET', '/api/jobs/' . $jobId, ['Authorization' => 'Bearer ' . $token]);

$dispatch('POST', '/api/jobs/enqueue', $auth, '{"delay_ms":5}');
$dispatch('POST', '/api/jobs/process-next', ['Authorization' => 'Bearer ' . $token]);
