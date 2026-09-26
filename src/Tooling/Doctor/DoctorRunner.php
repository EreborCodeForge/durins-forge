<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

use App\Tooling\Doctor\Checks\CompiledArtifactsCheck;
use App\Tooling\Doctor\Checks\EregionRuntimeCheck;
use App\Tooling\Doctor\Checks\MithrilPackageCheck;
use App\Tooling\Doctor\Checks\PhpExtensionsCheck;
use App\Tooling\Doctor\Checks\PhpVersionCheck;
use App\Tooling\Doctor\Checks\ProjectComposerCheck;
use App\Tooling\Doctor\Checks\ProjectKernelCheck;
use App\Tooling\Doctor\Checks\ProjectManifestCheck;

final class DoctorRunner
{
    /**
     * @param list<Check>|null $checks
     */
    public function __construct(
        private readonly ?array $checks = null,
    ) {}

    public function run(DoctorContext $context): DoctorReport
    {
        $results = [];
        foreach ($this->checks() as $check) {
            foreach ($check->run($context) as $result) {
                $results[] = $result;
            }
        }

        return new DoctorReport($results, $context->strict);
    }

    /**
     * @return list<Check>
     */
    private function checks(): array
    {
        return $this->checks ?? [
            new PhpVersionCheck(),
            new PhpExtensionsCheck(),
            new ProjectComposerCheck(),
            new ProjectKernelCheck(),
            new ProjectManifestCheck(),
            new CompiledArtifactsCheck(),
            new MithrilPackageCheck(),
            new EregionRuntimeCheck(),
        ];
    }
}
