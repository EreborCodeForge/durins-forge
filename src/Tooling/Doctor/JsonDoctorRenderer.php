<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

final class JsonDoctorRenderer implements DoctorRenderer
{
    public function render(DoctorReport $report): string
    {
        return json_encode(
            $report->toArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    }
}
