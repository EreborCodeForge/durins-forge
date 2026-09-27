<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

/**
 * Exit code contract (master §12 / SPEC-003):
 * 0 healthy (warnings OK unless --strict)
 * 1 warnings only when --strict
 * 2 invalid application configuration
 * 3 runtime incompatibility
 * 4 missing dependency
 */
final class DoctorExitCode
{
    public const int Healthy = 0;
    public const int StrictWarnings = 1;
    public const int InvalidConfiguration = 2;
    public const int RuntimeIncompatibility = 3;
    public const int MissingDependency = 4;
}
