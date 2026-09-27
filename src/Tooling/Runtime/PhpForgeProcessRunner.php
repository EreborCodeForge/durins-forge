<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

/**
 * Default process runner — spawns PHP + forge binary.
 */
final class PhpForgeProcessRunner implements RuntimeProcessRunner
{
    public function run(string $binary, array $arguments, string $workingDirectory): int
    {
        if (!is_file($binary)) {
            throw new RuntimeOrchestrationException("Forge binary not found: {$binary}");
        }

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binary);
        foreach ($arguments as $argument) {
            $cmd .= ' ' . escapeshellarg($argument);
        }

        $descriptor = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($cmd, $descriptor, $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeOrchestrationException('Unable to start forge process.');
        }

        return (int) proc_close($process);
    }
}
