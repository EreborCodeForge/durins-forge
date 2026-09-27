<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;

final class ProjectComposerCheck implements Check
{
    public function id(): string
    {
        return 'project.composer';
    }

    public function run(DoctorContext $context): array
    {
        $composer = $context->project->paths->composerJson();
        if (!is_file($composer)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'composer.json',
                    'missing',
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        $json = json_decode((string) file_get_contents($composer), true);
        if (!is_array($json)) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'composer.json',
                    'invalid JSON',
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        $php = $json['require']['php'] ?? null;
        $results = [CheckResult::ok($this->id(), 'composer.json', 'present')];

        if ($php !== '^8.5') {
            $results[] = CheckResult::fail(
                'project.composer.php',
                'composer php',
                'expected ^8.5, got ' . (is_string($php) ? $php : 'missing'),
                DoctorExitCode::InvalidConfiguration,
            );
        } else {
            $results[] = CheckResult::ok('project.composer.php', 'composer php', '^8.5');
        }

        $autoload = $context->root() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($autoload)) {
            $results[] = CheckResult::fail(
                'project.autoload',
                'autoload',
                'vendor/autoload.php missing (run composer install)',
                DoctorExitCode::MissingDependency,
            );
        } else {
            $results[] = CheckResult::ok('project.autoload', 'autoload', 'vendor/autoload.php');
        }

        return $results;
    }
}
