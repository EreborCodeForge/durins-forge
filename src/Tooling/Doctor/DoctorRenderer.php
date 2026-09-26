<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

interface DoctorRenderer
{
    public function render(DoctorReport $report): string;
}
