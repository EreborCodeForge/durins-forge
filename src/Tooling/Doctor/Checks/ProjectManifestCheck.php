<?php

declare(strict_types=1);

namespace App\Tooling\Doctor\Checks;

use App\Tooling\Doctor\Check;
use App\Tooling\Doctor\CheckResult;
use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorExitCode;
use App\Tooling\Project\DurinManifestException;
use App\Tooling\Project\DurinManifestParser;

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
