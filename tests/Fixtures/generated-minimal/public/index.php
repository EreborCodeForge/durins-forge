<?php

declare(strict_types=1);

/**
 * Minimal Durin app — Eregion worker entry via Mithril HttpApplication.
 */

$appRoot = dirname(__DIR__);
require $appRoot . '/vendor/autoload.php';

use App\Kernel;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use Erebor\Mithril\Runtime\Eregion\EregionBridge;
use Erebor\Mithril\Runtime\Eregion\Exceptions\ProtocolException;
use Erebor\Mithril\Runtime\Eregion\WorkerCliOptions;
use Erebor\Mithril\Runtime\Recycling\CompositeRecyclingPolicy;
use Erebor\Mithril\Runtime\Recycling\MaxRequestsPolicy;
use Erebor\Mithril\Runtime\Recycling\MemoryLimitPolicy;
use Erebor\Mithril\Runtime\Worker;
use Erebor\Mithril\Runtime\WorkerExitCode;
use Erebor\Mithril\Runtime\WorkerResult;
use Erebor\Mithril\Runtime\WorkerStopReason;
use Erebor\Mithril\Support\PackageVersion;

ApplicationPath::setRoot($appRoot);

if (PHP_SAPI !== 'cli') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'HTTP entry is Eregion. Run: vendor/bin/durin serve',
    ]);
    exit(1);
}

try {
    $options = WorkerCliOptions::fromArgv($argv);
} catch (ProtocolException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(WorkerExitCode::BootstrapFailure->value);
}

$bridge = new EregionBridge(
    socketPath: $options->socket,
    workerId: $options->workerId,
    generation: $options->generation,
    mithrilVersion: PackageVersion::mithril(),
);

$policy = new CompositeRecyclingPolicy(
    new MaxRequestsPolicy($options->maxRequests),
    new MemoryLimitPolicy($options->memoryLimitBytes()),
);

$worker = new Worker(
    app: new Kernel(),
    bridge: $bridge,
    maxRequests: $options->maxRequests,
    recyclingPolicy: $policy,
    memoryLimitBytes: $options->memoryLimitBytes(),
);

try {
    $result = $worker->runResult();
} catch (\Throwable) {
    $result = new WorkerResult(0, WorkerStopReason::BootstrapFailure);
} finally {
    $bridge->close();
}

exit($result->exitCode()->value);
