<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\WorkerGuard;

use Erebor\Mithril\Runtime\Guard\Report\GuardReport;

final class GuardReportWriter
{
    public function write(string $projectRoot, GuardReport $report): string
    {
        $dir = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'worker-guard';
        if (str_contains(str_replace('\\', '/', $dir), '/vendor/')) {
            throw new WorkerAuditException('refusing to write inside vendor');
        }
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new WorkerAuditException('unable to write report');
        }

        $json = json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new WorkerAuditException('unable to write report');
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'latest.json';
        if (file_put_contents($file, $json . PHP_EOL) === false) {
            throw new WorkerAuditException('unable to write report');
        }

        @chmod($dir, 0700);
        @chmod($file, 0600);

        return $file;
    }
}
