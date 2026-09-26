<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

final class TextDoctorRenderer implements DoctorRenderer
{
    public function render(DoctorReport $report): string
    {
        $lines = ['Durin Doctor', ''];

        foreach ($report->results as $result) {
            $mark = match ($result->status) {
                CheckStatus::Ok => 'OK',
                CheckStatus::Warning => 'WARN',
                CheckStatus::Fail => 'FAIL',
            };
            $label = str_pad($result->label, 22, '.', STR_PAD_RIGHT);
            $lines[] = sprintf('%s %s [%s]', $label, $result->detail, $mark);
        }

        $lines[] = '';
        $lines[] = 'Result: ' . $report->summary();
        $lines[] = 'Exit: ' . $report->exitCode();

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
