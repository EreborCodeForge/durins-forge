<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use Erebor\Mithril\Runtime\Guard\GuardConfig;
use Erebor\Mithril\Runtime\Guard\GuardConfigException;
use Erebor\Mithril\Runtime\Guard\GuardMode;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;

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
                CheckResult::warning(
                    $this->id(),
                    'WorkerGuard',
                    'WorkerGuard invalid configuration; guard disabled for this worker. Run `durin doctor --strict`',
                ),
            ];
        }

        if ($config->mode === GuardMode::Audit) {
            return [
                CheckResult::warning(
                    $this->id(),
                    'WorkerGuard',
                    'mode=audit is not valid for the HTTP worker. Run `durin doctor --strict`',
                ),
            ];
        }

        return [CheckResult::ok($this->id(), 'WorkerGuard', 'mode ' . $config->mode->value)];
    }
}
