<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use Erebor\Mithril\Runtime\Guard\GuardConfig;
use Erebor\Mithril\Runtime\Guard\GuardConfigException;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;

final class WorkerGuardConfigCheck implements Check
{
    public function id(): string
    {
        return 'runtime.worker_guard';
    }

    public function run(DoctorContext $context): array
    {
        $path = $context->root() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'worker_guard.php';
        if (!is_file($path)) {
            return [CheckResult::ok($this->id(), 'WorkerGuard', 'not configured; mode stays off')];
        }

        try {
            $config = GuardConfig::loadFile($path, false);
        } catch (GuardConfigException) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'WorkerGuard',
                    'config/worker_guard.php is invalid',
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        return [CheckResult::ok($this->id(), 'WorkerGuard', 'mode ' . $config->mode->value)];
    }
}
