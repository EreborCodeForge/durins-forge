<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use Composer\InstalledVersions;

final class MithrilPackageCheck implements Check
{
    public function id(): string
    {
        return 'runtime.mithril';
    }

    public function run(DoctorContext $context): array
    {
        if (!class_exists(InstalledVersions::class)) {
            return [
                CheckResult::warning($this->id(), 'MithrilPHP', 'Composer InstalledVersions unavailable'),
            ];
        }

        if (!InstalledVersions::isInstalled('ereborcodeforge/mithrilphp')) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'MithrilPHP',
                    'package not installed',
                    DoctorExitCode::MissingDependency,
                ),
            ];
        }

        $version = InstalledVersions::getPrettyVersion('ereborcodeforge/mithrilphp') ?? 'unknown';

        return [CheckResult::ok($this->id(), 'MithrilPHP', $version)];
    }
}
