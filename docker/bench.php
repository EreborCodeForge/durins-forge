<?php

declare(strict_types=1);

/**
 * Benchmark sequencial live vs compiled (mesmo estilo do teste local).
 * Uso: php bench.php [live|compiled|both]
 */

$mode = $argv[1] ?? getenv('BENCH_TARGET') ?: 'both';
$n = (int) (getenv('BENCH_REQUESTS') ?: 300);
$warmup = (int) (getenv('BENCH_WARMUP') ?: 30);

$targets = [
    'live' => getenv('LIVE_URL') ?: 'http://127.0.0.1:8082/api/health',
    'compiled' => getenv('COMPILED_URL') ?: 'http://127.0.0.1:8081/api/health',
];

function waitFor(string $url, int $seconds = 60): void
{
    $deadline = time() + $seconds;
    while (time() < $deadline) {
        $body = @file_get_contents($url);
        if ($body !== false && str_contains($body, '"status":"ok"')) {
            return;
        }
        usleep(250_000);
    }
    fwrite(STDERR, "Timeout waiting for {$url}\n");
    exit(1);
}

function bench(string $label, string $url, int $n, int $warmup): array
{
    waitFor($url);
    for ($i = 0; $i < $warmup; $i++) {
        file_get_contents($url);
    }

    $ok = 0;
    $t0 = microtime(true);
    for ($i = 0; $i < $n; $i++) {
        $body = file_get_contents($url);
        if ($body !== false && str_contains($body, '"status":"ok"')) {
            $ok++;
        }
    }
    $elapsed = microtime(true) - $t0;
    $rps = $n / max($elapsed, 0.0001);
    $avg = ($elapsed / $n) * 1000;

    printf(
        "=== %s ===\nurl: %s\nrequests: %d | ok: %d | tempo: %.3fs | req/s: %.1f | avg: %.2f ms\n\n",
        strtoupper($label),
        $url,
        $n,
        $ok,
        $elapsed,
        $rps,
        $avg
    );

    return ['label' => $label, 'rps' => $rps, 'avg' => $avg, 'ok' => $ok, 'n' => $n];
}

$run = [];
if ($mode === 'both' || $mode === 'live') {
    $run[] = bench('live (não compilado)', $targets['live'], $n, $warmup);
}
if ($mode === 'both' || $mode === 'compiled') {
    $run[] = bench('compiled (loadCompiled)', $targets['compiled'], $n, $warmup);
}

if (count($run) === 2 && $run[0]['rps'] > 0) {
    $a = $run[0];
    $b = $run[1];
    $delta = (($b['rps'] - $a['rps']) / $a['rps']) * 100;
    printf(
        "--- comparação ---\nlive: %.1f req/s | compiled: %.1f req/s | Δ compiled vs live: %+.1f%%\n",
        $a['rps'],
        $b['rps'],
        $delta
    );
}
