<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

/**
 * Spawns a native binary with an argument vector. Does not prefix the PHP interpreter.
 */
final class NativeProcessRunner implements RuntimeProcessRunner
{
    public function run(string $binary, array $arguments, string $workingDirectory): int
    {
        if (!is_file($binary)) {
            throw new RuntimeOrchestrationException("Binary not found: {$binary}");
        }

        $process = proc_open(
            array_merge([$binary], $arguments),
            [0 => STDIN, 1 => STDOUT, 2 => STDERR],
            $pipes,
            $workingDirectory,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            throw new RuntimeOrchestrationException('Unable to start native process.');
        }

        return (int) proc_close($process);
    }
}
