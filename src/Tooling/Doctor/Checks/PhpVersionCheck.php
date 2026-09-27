<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;

final class PhpVersionCheck implements Check
{
    public function id(): string
    {
        return 'php.version';
    }

    public function run(DoctorContext $context): array
    {
        if (PHP_VERSION_ID < 80500) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'PHP',
                    'PHP 8.5+ required (found ' . PHP_VERSION . ')',
                    DoctorExitCode::RuntimeIncompatibility,
                ),
            ];
        }

        return [CheckResult::ok($this->id(), 'PHP', PHP_VERSION)];
    }
}
