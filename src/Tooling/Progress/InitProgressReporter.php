<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Progress;

/**
 * Emits machine-readable init progress (JSONL) or human lines.
 */
final class InitProgressReporter
{
    public function __construct(
        private readonly bool $jsonl = false,
        private readonly mixed $stdout = null,
    ) {}

    public function stage(string $stage, string $message): void
    {
        if ($this->jsonl) {
            $this->writeJson([
                'type' => 'progress',
                'stage' => $stage,
                'message' => $message,
            ]);

            return;
        }

        $this->writeLine($message);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function complete(string $preset, string $runner, array $extra = []): void
    {
        if ($this->jsonl) {
            $this->writeJson(array_merge([
                'type' => 'complete',
                'preset' => $preset,
                'runner' => $runner,
            ], $extra));

            return;
        }

        $this->writeLine("Initialized preset {$preset} (runner: {$runner}).");
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(array $payload): void
    {
        $this->writeLine(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function writeLine(string $message): void
    {
        if ($this->stdout !== null) {
            fwrite($this->stdout, $message . PHP_EOL);

            return;
        }

        echo $message . PHP_EOL;
    }
}
