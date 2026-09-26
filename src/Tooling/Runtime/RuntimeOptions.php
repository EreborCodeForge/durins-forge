<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

/**
 * Shared options for serve / dev / status orchestration.
 */
final readonly class RuntimeOptions
{
    /**
     * @param list<string> $passthroughArgs extra CLI args forwarded to forge
     */
    public function __construct(
        public string $workingDirectory,
        public string $host = '0.0.0.0',
        public int $port = 8080,
        public int $workers = 0,
        public ?string $environment = null,
        public array $passthroughArgs = [],
        public RuntimeMode $mode = RuntimeMode::Serve,
        public bool $preferPhpServer = false,
    ) {}

    /**
     * @param list<string> $argv args after the command name
     */
    public static function fromArgv(array $argv, string $workingDirectory, RuntimeMode $mode = RuntimeMode::Serve): self
    {
        $host = $mode === RuntimeMode::Dev ? '127.0.0.1' : '0.0.0.0';
        $port = 8080;
        $workers = 0;
        $environment = $mode === RuntimeMode::Dev ? 'development' : null;
        $preferPhp = false;
        $passthrough = [];

        foreach ($argv as $arg) {
            if ($arg === '--php') {
                $preferPhp = true;
                continue;
            }
            if ($arg === '--eregion') {
                $preferPhp = false;
                continue;
            }
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
            mode: $mode,
            preferPhpServer: $preferPhp,
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
