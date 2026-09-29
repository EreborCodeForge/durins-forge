<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

/**
 * Options for `durin run` — configuration-driven execution, not preset-aware.
 *
 * @param list<string> $passthroughArgs
 */
final readonly class RunOptions
{
    /**
     * @param list<string> $passthroughArgs
     */
    public function __construct(
        public string $workingDirectory,
        public string $host = '0.0.0.0',
        public int $port = 8080,
        public int $workers = 0,
        public ?string $environment = null,
        public array $passthroughArgs = [],
    ) {}

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv, string $workingDirectory): self
    {
        $host = '0.0.0.0';
        $port = 8080;
        $workers = 0;
        $environment = null;
        $passthrough = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
                continue;
            }
            if (str_starts_with($arg, '--port=')) {
                $port = (int) substr($arg, 7);
                continue;
            }
            if (str_starts_with($arg, '--workers=')) {
                $workers = (int) substr($arg, 10);
                continue;
            }
            if (str_starts_with($arg, '--env=')) {
                $environment = substr($arg, 6);
                continue;
            }
            $passthrough[] = $arg;
        }

        return new self(
            workingDirectory: $workingDirectory,
            host: $host,
            port: $port,
            workers: $workers,
            environment: $environment,
            passthroughArgs: $passthrough,
        );
    }

    /**
     * @return list<string>
     */
    public function forgeServeArgs(): array
    {
        $args = [
            '--host=' . $this->host,
            '--port=' . (string) $this->port,
        ];

        if ($this->workers > 0) {
            $args[] = '--workers=' . (string) $this->workers;
        }

        if ($this->environment !== null && $this->environment !== '') {
            $args[] = '--env=' . $this->environment;
        }

        return array_merge($args, $this->passthroughArgs);
    }
}
