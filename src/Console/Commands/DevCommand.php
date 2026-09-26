<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Runtime\DevRuntimeSummary;
use App\Tooling\Runtime\MithrilRuntimeFacade;
use App\Tooling\Runtime\RuntimeFacade;
use App\Tooling\Runtime\RuntimeMode;
use App\Tooling\Runtime\RuntimeOptions;
use App\Tooling\Runtime\RuntimeOrchestrationException;
use Erebor\Mithril\Console\Command;

/**
 * Development entry: local defaults + RuntimeFacade::dev (distinct from production serve).
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
        return 'Sobe o app em modo desenvolvimento (Eregion local; use --php para serve:php)';
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
