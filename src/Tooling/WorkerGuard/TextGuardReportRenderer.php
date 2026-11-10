<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\WorkerGuard;

use Erebor\Mithril\Runtime\Guard\Report\GuardReport;

final class TextGuardReportRenderer
{
    public function render(GuardReport $report): string
    {
        $summary = $report->toArray()['summary'];
        $lines = [
            'WorkerGuard audit',
            'result: ' . $report->result,
            'cycles: ' . $summary['cycles'],
            'warnings: ' . $summary['warnings'],
            'criticals: ' . $summary['criticals'],
        ];

        foreach ($report->probeCoverage as $row) {
            $lines[] = 'probe ' . $row['id'] . ': ' . $row['status'];
        }
        foreach ($report->findings as $finding) {
            $lines[] = $finding->probeId . ' ' . $finding->code . ' ' . $finding->severity->value;
            $lines[] = $finding->safeSummary;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
