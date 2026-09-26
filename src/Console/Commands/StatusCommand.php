<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Runtime\JsonRuntimeStatusRenderer;
use App\Tooling\Runtime\MithrilRuntimeFacade;
use App\Tooling\Runtime\RuntimeFacade;
use App\Tooling\Runtime\RuntimeMode;
use App\Tooling\Runtime\RuntimeOptions;
use App\Tooling\Runtime\TextRuntimeStatusRenderer;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

/**
 * Reports what IS configured/available now (distinct from `doctor` can-run checks).
 * --watch is explicitly out of scope for SPEC-007.
 */
final class StatusCommand extends Command
{
    public function __construct(
        private readonly ?RuntimeFacade $facade = null,
    ) {}

    public static function getSignature(): string
    {
        return 'status';
    }

    public static function getDescription(): string
    {
        return 'Mostra status do runtime Mithril/Eregion (config/manifest; sem --watch)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        if (isset($parsed['options']['watch'])) {
            $this->error('status --watch is not implemented yet. Use `durin status` (one-shot).');

            return 2;
        }

        $json = ($parsed['options']['json'] ?? false) === true;
        $cwd = getcwd() ?: base_path();
        $options = RuntimeOptions::fromArgv($this->args, $cwd, RuntimeMode::Serve);
        $facade = $this->facade ?? new MithrilRuntimeFacade();
        $status = $facade->status($options);

        $renderer = $json ? new JsonRuntimeStatusRenderer() : new TextRuntimeStatusRenderer();
        echo $renderer->render($status);

        return $status->available ? 0 : 1;
    }
}
