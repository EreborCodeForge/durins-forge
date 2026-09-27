<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

interface DoctorRenderer
{
    public function render(DoctorReport $report): string;
}
