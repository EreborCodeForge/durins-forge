<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\DevRuntimeSummary;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilRuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeMode;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOrchestrationException;
use Erebor\Mithril\Console\Command;

/**
 * Development entry: local defaults + RuntimeFacade::dev (HTTP experience).
 * Job apps must use `durin run`. Future: watch/reload/logs.
 */
final class DevCommand extends Command
{
    public function __construct(
        private readonly ?RuntimeFacade $facade = null,
        private readonly ?DevRuntimeSummary $summary = null,
    ) {}

    public static function getSignature(): string
    {
        return 'dev';
    }

    public static function getDescription(): string
    {
        return 'Sobe o app HTTP em desenvolvimento (Eregion local; use --php para serve:php)';
    }

    public function execute(): int
    {
        $cwd = getcwd() ?: base_path();
        $options = RuntimeOptions::fromArgv($this->args, $cwd, RuntimeMode::Dev);
        $facade = $this->facade ?? new MithrilRuntimeFacade();
        $summary = $this->summary ?? new DevRuntimeSummary();

        $this->line($summary->render($options));

        if (!is_file($cwd . DIRECTORY_SEPARATOR . '.env')) {
            $this->error('Missing .env — copy .env.example before running dev.');
            return 2;
        }

        try {
            return $facade->dev($options);
        } catch (RuntimeOrchestrationException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
