<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Console\InitOptionReader;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Progress\InitProgressReporter;
use EreborCodeForge\Durin\Presets\Capability\CapabilityCatalog;
use EreborCodeForge\Durin\Presets\Capability\IncompatibleCapabilityException;
use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionException;
use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionPolicy;
use EreborCodeForge\Durin\Presets\Preset\UnknownPresetException;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
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
        return 'Inicializa a aplicação com um preset, capabilities e supervisão opcional';
    }

    public function execute(): int
    {
        $reader = new InitOptionReader();
        try {
            $options = $reader->read($this->args);
        } catch (WorkloadOptionException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $preset = $reader->optionalString($options, 'preset');
        $progressMode = $reader->optionalString($options, 'progress') ?? 'text';
        $skipRuntime = array_key_exists('skip-runtime-install', $options);

        if ($progressMode !== 'text' && $progressMode !== 'jsonl') {
            $this->error('Unknown --progress. Use text or jsonl.');

            return 2;
        }

        $jsonl = $progressMode === 'jsonl';
        try {
            $selection = $this->selectionFrom($options, $reader, $preset);
        } catch (UnknownPresetException | IncompatibleCapabilityException | WorkloadOptionException $e) {
            $this->reportUsage($e->getMessage(), $jsonl);

            return 2;
        }

        try {
            $root = ApplicationPath::root();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $progress = new InitProgressReporter(jsonl: $jsonl);
        $initializer = $this->initializer ?? new ApplicationInitializer();

        try {
            $result = $initializer->initialize(
                applicationRoot: $root,
                presetId: $preset,
                progress: $progress,
                skipRuntimeInstall: $skipRuntime,
                selection: $selection,
            );
        } catch (UnknownPresetException | IncompatibleCapabilityException | WorkloadOptionException $e) {
            $this->reportUsage($e->getMessage(), $jsonl);

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

    private function reportUsage(string $message, bool $jsonl): void
    {
        if (!$jsonl) {
            $this->error($message);

            return;
        }

        $this->line(json_encode([
            'type' => 'error',
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string|bool> $options
     */
    private function selectionFrom(array $options, InitOptionReader $reader, ?string $preset): \EreborCodeForge\Durin\Core\Contract\ProjectSelection
    {
        $registry = (new DefaultPresetRegistryFactory())->create();
        $definition = ($preset === null || $preset === '')
            ? $registry->default()
            : $registry->definition($preset);
        $catalog = new CapabilityCatalog();
        $features = $catalog->normalize($reader->optionalString($options, 'with'));
        $catalog->assertCompatible($definition->id(), $definition->runtime()->mode, $features);

        return (new WorkloadOptionPolicy())->compile(
            presetId: $definition->id(),
            mode: $definition->runtime()->mode,
            features: $features,
            supervisorChoice: $reader->optionalString($options, 'supervisor') ?? 'auto',
            scalingStrategy: $reader->optionalString($options, 'scaling'),
            workersMin: $reader->optionalInt($options, 'workers-min'),
            workersMax: $reader->optionalInt($options, 'workers-max'),
            resourceClass: $reader->optionalString($options, 'resource-class'),
            memoryMb: $reader->optionalInt($options, 'memory-mb'),
        );
    }
}
