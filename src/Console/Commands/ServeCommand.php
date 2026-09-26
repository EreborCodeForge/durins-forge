<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Runtime\MithrilRuntimeFacade;
use App\Tooling\Runtime\RuntimeFacade;
use App\Tooling\Runtime\RuntimeMode;
use App\Tooling\Runtime\RuntimeOptions;
use App\Tooling\Runtime\RuntimeOrchestrationException;
use Erebor\Mithril\Console\Command;

/**
 * Production-oriented runtime entry via shared RuntimeFacade (ADR-0002).
 * Delegates to Mithril forge serve → Eregion; does not fork the server.
 */
final class ServeCommand extends Command
{
    public function __construct(
        private readonly ?RuntimeFacade $facade = null,
    ) {}

    public static function getSignature(): string
    {
        return 'serve';
    }

    public static function getDescription(): string
    {
        return 'Sobe o runtime de produção via Mithril/Eregion (forge serve)';
    }

    public function execute(): int
    {
        $cwd = getcwd() ?: base_path();
        $options = RuntimeOptions::fromArgv($this->args, $cwd, RuntimeMode::Serve);
        $facade = $this->facade ?? new MithrilRuntimeFacade();

        try {
            return $facade->serve($options);
        } catch (RuntimeOrchestrationException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
