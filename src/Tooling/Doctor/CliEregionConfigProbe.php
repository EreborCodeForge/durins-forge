<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

/**
 * Runs `eregion check` with an argument vector and a deadline. Does not read environment values.
 */
final class CliEregionConfigProbe implements EregionConfigProbe
{
    public function __construct(
        private readonly float $timeoutSeconds = 2.0,
    ) {}

    public function probe(string $binary, string $configPath): EregionConfigReport
    {
        if (!is_file($binary)) {
            return new EregionConfigReport(false, 'eregion binary unavailable', [], 'unknown');
        }

        $process = proc_open(
            [$binary, 'check', '--config=' . $configPath],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );
        if (!is_resource($process)) {
            return new EregionConfigReport(false, 'eregion check could not start', [], 'unknown');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $this->timeoutSeconds;
        $timedOut = false;
        $emptyReads = 0;
        while (microtime(true) < $deadline) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $timedOut = $stdout === '' && $stderr === '';
                break;
            }
            $ready = stream_select($read, $write, $except, (int) $remaining, (int) (($remaining - (int) $remaining) * 1_000_000));
            if ($ready === false) {
                break;
            }
            if ($ready === 0) {
                $timedOut = $stdout === '' && $stderr === '';
                break;
            }
            $got = false;
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 8192);
                if (!is_string($chunk) || $chunk === '') {
                    continue;
                }
                $got = true;
                if ($pipe === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
            if ($got) {
                $emptyReads = 0;
                continue;
            }
            ++$emptyReads;
            if ($emptyReads >= 2 && ($stdout !== '' || $stderr !== '')) {
                break;
            }
        }

        if ($timedOut) {
            $this->kill($process);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($timedOut) {
            return new EregionConfigReport(false, 'eregion check timed out', [], 'unknown');
        }

        return EregionConfigReport::fromOutput($exit, $stdout, $stderr);
    }

    private function kill(mixed $process): void
    {
        $status = proc_get_status($process);
        $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;
        if (PHP_OS_FAMILY === 'Windows' && $pid > 0) {
            exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        } else {
            proc_terminate($process);
        }
    }
}
