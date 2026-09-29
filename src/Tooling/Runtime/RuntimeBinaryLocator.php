<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

/**
 * Resolves vendor binaries used by runtime launchers.
 */
final class RuntimeBinaryLocator
{
    public function forge(string $workingDirectory, ?string $override = null): string
    {
        if ($override !== null) {
            return $override;
        }

        return $this->firstExisting($workingDirectory, ['forge', 'forge.bat'])
            ?? throw new RuntimeLaunchException(
                'vendor/bin/forge not found. Run composer install.'
            );
    }

    public function jobWorker(string $workingDirectory, ?string $override = null): string
    {
        if ($override !== null) {
            return $override;
        }

        return $this->firstExisting($workingDirectory, ['job-worker', 'job-worker.bat'])
            ?? throw new RuntimeLaunchException(
                'vendor/bin/job-worker not found. Require mithrilphp ^3.0 and run composer install.'
            );
    }

    /**
     * @param list<string> $names
     */
    private function firstExisting(string $workingDirectory, array $names): ?string
    {
        $bin = $workingDirectory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        foreach ($names as $name) {
            $candidate = $bin . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
