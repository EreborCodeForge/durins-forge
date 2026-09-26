<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

interface Check
{
    public function id(): string;

    /**
     * @return list<CheckResult>
     */
    public function run(DoctorContext $context): array;
}
