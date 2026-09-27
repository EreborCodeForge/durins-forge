<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Check;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckResult;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorExitCode;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;

final class ProjectManifestCheck implements Check
{
    public function __construct(
        private readonly DurinManifestParser $parser = new DurinManifestParser(),
    ) {}

    public function id(): string
    {
        return 'project.manifest';
    }

    public function run(DoctorContext $context): array
    {
        $path = $context->project->paths->durinYaml();
        if (!is_file($path)) {
            return [
                CheckResult::warning(
                    $this->id(),
                    'durin.yaml',
                    'not present (optional until presets land)',
                ),
            ];
        }

        try {
            $manifest = $this->parser->parseFile($path);
        } catch (DurinManifestException $e) {
            return [
                CheckResult::fail(
                    $this->id(),
                    'durin.yaml',
                    $e->getMessage(),
                    DoctorExitCode::InvalidConfiguration,
                ),
            ];
        }

        return [
            CheckResult::ok(
                $this->id(),
                'durin.yaml',
                $manifest->applicationName . ' / preset=' . $manifest->preset,
            ),
        ];
    }
}
