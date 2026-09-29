<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilRuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeMode;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOrchestrationException;
use Erebor\Mithril\Console\Command;

/**
 * HTTP-only production entry via RuntimeFacade → forge serve → Eregion.
 * Job apps must use `durin run`, not serve.
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
        return 'Sobe o runtime HTTP de produção via Mithril/Eregion (forge serve)';
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
