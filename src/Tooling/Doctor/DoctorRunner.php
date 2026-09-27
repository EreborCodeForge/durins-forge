<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\CompiledArtifactsCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\EregionRuntimeCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\MithrilPackageCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\PhpExtensionsCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\PhpVersionCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\ProjectComposerCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\ProjectKernelCheck;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\ProjectManifestCheck;

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
