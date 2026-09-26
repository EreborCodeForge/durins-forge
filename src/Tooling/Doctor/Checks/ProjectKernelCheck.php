<?php

declare(strict_types=1);

namespace App\Tooling\Doctor\Checks;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;
use Erebor\Mithril\Contracts\HttpApplication;

final class ProjectKernelCheck implements Check
{
    public function id(): string
    {
        return 'project.kernel';
    }

    public function run(DoctorContext $context): array
    {
        $composerPath = $context->project->paths->composerJson();
        $kernelClass = 'App\\Kernel';

        if (is_file($composerPath)) {
            $json = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($json)) {
                $configured = $json['extra']['mithril']['kernel'] ?? null;
                if (is_string($configured) && $configured !== '') {
                    $kernelClass = $configured;
                }
            }
        }

        if (!class_exists($kernelClass)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'Kernel',
                    "class not loadable: {$kernelClass}",
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        if (!is_subclass_of($kernelClass, HttpApplication::class)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'Kernel',
                    "{$kernelClass} must implement " . HttpApplication::class,
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        return [CheckResult::ok($this->id(), 'Kernel', $kernelClass)];
    }
}
