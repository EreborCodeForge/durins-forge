<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Progress\InitProgressReporter;
use EreborCodeForge\Durin\Presets\Preset\UnknownPresetException;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

/**
 * Initializes the current application root with a preset from durin-presets.
 */
final class InitCommand extends Command
{
    public function __construct(
        private readonly ?ApplicationInitializer $initializer = null,
    ) {}

    public static function getSignature(): string
    {
        return 'init';
    }

    public static function getDescription(): string
    {
        return 'Inicializa a aplicação com um preset (default do registry se omitido)';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $preset = ArgParser::string($parsed['options'], 'preset');
        $progressMode = ArgParser::string($parsed['options'], 'progress', 'text') ?? 'text';
        $skipRuntime = array_key_exists('skip-runtime-install', $parsed['options']);

        if ($progressMode !== 'text' && $progressMode !== 'jsonl') {
            $this->error('Unknown --progress. Use text or jsonl.');

            return 2;
        }

        try {
            $root = ApplicationPath::root();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $jsonl = $progressMode === 'jsonl';
        $progress = new InitProgressReporter(jsonl: $jsonl);
        $initializer = $this->initializer ?? new ApplicationInitializer();

        try {
            $result = $initializer->initialize(
                applicationRoot: $root,
                presetId: $preset,
                progress: $progress,
                skipRuntimeInstall: $skipRuntime,
            );
        } catch (UnknownPresetException $e) {
            if (!$jsonl) {
                $this->error($e->getMessage());
            } else {
                $this->line(json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }

            return 2;
        } catch (\Throwable $e) {
            if (!$jsonl) {
                $this->error($e->getMessage());
            } else {
                $this->line(json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage(),
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }

            return 1;
        }

        if (!$jsonl && $result['idempotent']) {
            $this->info("Already initialized with preset {$result['preset']}.");
        }

        return 0;
    }
}
