<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Init;

use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Contract\ProjectSelection;
use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Mutation\ScaffoldWriter;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldActionType;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use EreborCodeForge\Durin\Forge\Tooling\Progress\InitProgressReporter;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\ManagedJobWorkload;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\ManifestRuntimeFinalizer;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProvisioner;
use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionPolicy;
use EreborCodeForge\Durin\Presets\Contract\PresetDefinition;
use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;
use EreborCodeForge\Durin\Presets\Preset\UnknownPresetException;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Presets\Registry\PresetEngine;
use EreborCodeForge\Durin\Presets\Registry\PresetRegistry;

/**
 * Initializes a neutral Durin application root with a preset (not architecture migration).
 */
final class ApplicationInitializer
{
    private const string UNINITIALIZED = 'uninitialized';

    public function __construct(
        private readonly ?PresetEngine $engine = null,
        private readonly ScaffoldWriter $writer = new ScaffoldWriter(),
        private readonly RuntimeProvisioner $runtime = new RuntimeProvisioner(),
        private readonly DurinManifestParser $manifestParser = new DurinManifestParser(),
        private readonly ProjectDiscovery $discovery = new ProjectDiscovery(),
        private readonly ManifestRuntimeFinalizer $manifestFinalizer = new ManifestRuntimeFinalizer(),
        private readonly ComposerManifestMerger $composerMerger = new ComposerManifestMerger(),
        private readonly EnvExampleMerger $envMerger = new EnvExampleMerger(),
        private readonly ResidualRuntimeCleaner $residualCleaner = new ResidualRuntimeCleaner(),
    ) {}

    /**
     * @return array{preset: string, runtime: RuntimePlan, idempotent: bool}
     */
    public function initialize(
        string $applicationRoot,
        ?string $presetId,
        InitProgressReporter $progress,
        bool $skipRuntimeInstall = false,
        ?ProjectSelection $selection = null,
    ): array {
        $root = $this->assertApplicationRoot($applicationRoot);
        $engine = $this->engine ?? (new DefaultPresetRegistryFactory())->engine();
        $registry = $engine->registry();

        $progress->stage('preset.resolve', 'Resolving preset');
        $definition = $this->resolvePreset($registry, $presetId);
        $selection ??= new ProjectSelection($definition->id());

        $existing = $this->readExistingPreset($root);
        if ($existing !== null && $existing !== self::UNINITIALIZED) {
            if ($existing === $definition->id()) {
                $manifest = $this->manifestParser->parseFile($root . DIRECTORY_SEPARATOR . 'durin.yaml');
                $this->assertRerunCompatible($root, $manifest, $selection);
                $progress->stage('runtime.resolve', 'Resolving runtime');
                $plan = RuntimePlan::fromManifest($manifest);

                if (!$skipRuntimeInstall && $plan->usesEregion() && $this->runtime->shouldInstall($definition->runtime(), $plan)) {
                    $progress->stage('runtime.provision', 'Provisioning runtime');
                    $progress->stage('runtime.configure', 'Configuring runtime');
                    $this->runtime->provision(
                        $root,
                        $definition->runtime(),
                        false,
                        $plan,
                        $selection,
                        true,
                    );
                }

                $progress->stage('validate', 'Validating application');
                $progress->complete($definition->id(), $plan, [
                    'features' => $manifest->features,
                    'idempotent' => true,
                ]);

                return [
                    'preset' => $definition->id(),
                    'runtime' => $plan,
                    'idempotent' => true,
                ];
            }

            throw new \RuntimeException(
                "Application already initialized with preset \"{$existing}\". "
                . "Requested \"{$definition->id()}\". Use a future evolve/migrate command to change presets."
            );
        }

        $progress->stage('scaffold.plan', 'Preparing scaffold');
        $appName = $this->resolveApplicationName($root);
        $options = new ProjectOptions(
            name: $appName,
            preset: $definition->id(),
            targetDirectory: $root,
            extra: ['with' => $selection->features],
            selection: $selection,
        );
        $fullPlan = $engine->plan($options);
        $plannedComposer = $this->extractPlannedContents($fullPlan, 'composer.json');
        $plannedEnv = $this->extractPlannedContents($fullPlan, '.env.example');
        $plan = $this->filterPlanForExistingRoot($root, $fullPlan);
        $this->clearReplaceableConflicts($root, $plan);

        $progress->stage('runtime.resolve', 'Resolving runtime');
        $profile = $this->effectiveProfile($definition, $selection);
        $runtimePlan = $this->runtime->resolve($profile);
        $expectedSupervisor = (new WorkloadOptionPolicy())->effectiveSupervisor(
            $profile->mode,
            $selection->supervisorChoice,
        );
        if ($runtimePlan->supervisor !== $expectedSupervisor) {
            throw new \RuntimeException(
                'Resolved supervisor does not match the requested --supervisor value. No compatible runtime is available.'
            );
        }

        $progress->stage('scaffold.apply', 'Applying scaffold');
        $result = $this->writer->write($root, $plan);
        if (!$result->ok) {
            $conflicts = array_map(
                static fn ($c) => $c->relativePath . ' (' . $c->reason . ')',
                $result->conflicts,
            );
            throw new \RuntimeException('Scaffold conflicts: ' . implode(', ', $conflicts));
        }

        $this->applyMergedComposer($root, $plannedComposer, $runtimePlan);
        $this->applyMergedEnvExample($root, $plannedEnv);
        $this->residualCleaner->clean($root, $runtimePlan, $plan);

        if (!$skipRuntimeInstall && $this->runtime->shouldInstall($profile, $runtimePlan)) {
            $progress->stage('runtime.provision', 'Provisioning runtime');
            $progress->stage('runtime.configure', 'Configuring runtime');
            $this->runtime->provision($root, $profile, false, $runtimePlan, $selection);
        } elseif ($runtimePlan->usesEregion()) {
            $progress->stage('runtime.configure', 'Configuring runtime');
            $this->runtime->provision($root, $profile, true, $runtimePlan, $selection);
        }

        $progress->stage('manifest.finalize', 'Finalizing manifest');
        $manifest = $this->manifestFinalizer->finalize($root, $runtimePlan);

        $progress->stage('validate', 'Validating application');
        $this->assertNoVendorMutation($root);
        $progress->complete($definition->id(), $runtimePlan, [
            'features' => $manifest->features,
        ]);

        return [
            'preset' => $definition->id(),
            'runtime' => $runtimePlan,
            'idempotent' => false,
        ];
    }

    private function effectiveProfile(PresetDefinition $definition, ProjectSelection $selection): RuntimeProfile
    {
        $base = $definition->runtime();
        $preferred = $base->preferredRunner;
        $preferredCapabilities = $base->preferredCapabilities;
        if ($base->mode === 'job' && $selection->supervisorChoice === 'eregion') {
            $preferred = 'eregion';
        }
        if ($selection->supervisorChoice === 'none') {
            $preferred = null;
            $preferredCapabilities = [];
        }

        return new RuntimeProfile(
            mode: $base->mode,
            requiredCapabilities: $base->requiredCapabilities,
            preferredCapabilities: $preferredCapabilities,
            preferredRunner: $preferred,
            installRunner: $base->installRunner,
        );
    }

    private function assertRerunCompatible(string $root, DurinManifest $manifest, ProjectSelection $selection): void
    {
        $persisted = [];
        if ($manifest->features['consumer']) {
            $persisted[] = 'consumer';
        }
        if ($manifest->features['producer']) {
            $persisted[] = 'producer';
        }
        if ($selection->features !== [] && $selection->features !== $persisted) {
            throw new \RuntimeException(
                'Init cannot change capabilities on an existing application. Use a future configure/evolve command.'
            );
        }

        if ($selection->supervisorChoice === 'none' && $manifest->runtime?->supervisor !== null) {
            throw new \RuntimeException(
                'Init cannot change supervisor on an existing application. Use a future configure/evolve command.'
            );
        }
        if ($selection->supervisorChoice === 'eregion' && $manifest->runtime?->supervisor !== 'eregion') {
            throw new \RuntimeException(
                'Init cannot change supervisor on an existing application. Use a future configure/evolve command.'
            );
        }

        $workloadRequested = $selection->scalingStrategy !== null
            || $selection->workersMin !== null
            || $selection->workersMax !== null
            || $selection->resourceClass !== null
            || $selection->memoryMb !== null;
        if (!$workloadRequested) {
            return;
        }

        $path = $root . DIRECTORY_SEPARATOR . 'eregion.yaml';
        if (!is_file($path)) {
            throw new \RuntimeException(
                'Init cannot apply workload options because eregion.yaml has no managed workload. Use a future configure/evolve command.'
            );
        }

        $current = ManagedJobWorkload::parseDocument((string) file_get_contents($path));
        $desired = ManagedJobWorkload::desired($selection);
        if ($current != $desired) {
            throw new \RuntimeException(
                'Init cannot change workload settings on an existing application. Use a future configure/evolve command.'
            );
        }
    }

    private function resolvePreset(PresetRegistry $registry, ?string $presetId): PresetDefinition
    {
        try {
            if ($presetId === null || $presetId === '') {
                return $registry->default();
            }

            return $registry->definition($presetId);
        } catch (UnknownPresetException $e) {
            throw $e;
        }
    }

    private function assertApplicationRoot(string $applicationRoot): string
    {
        $resolved = realpath($applicationRoot) ?: $applicationRoot;
        if (!is_dir($resolved)) {
            throw new \RuntimeException("Application root is not a directory: {$applicationRoot}");
        }

        $normalized = strtolower(str_replace('\\', '/', $resolved));
        if (str_contains($normalized, '/vendor/ereborcodeforge/durins-forge')
            || str_contains($normalized, '/vendor/ereborcodeforge/durin-forge')) {
            throw new \RuntimeException('Refusing to initialize inside the Durin Forge package vendor path.');
        }

        if (!is_file($resolved . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new \RuntimeException(
                "Not a Durin application root (missing composer.json): {$resolved}"
            );
        }

        return $resolved;
    }

    private function readExistingPreset(string $root): ?string
    {
        $path = $root . DIRECTORY_SEPARATOR . 'durin.yaml';
        if (!is_file($path)) {
            return null;
        }

        try {
            $manifest = $this->manifestParser->parseFile($path);

            return $manifest->preset;
        } catch (DurinManifestException) {
            // Allow init to replace an invalid/incomplete uninitialized stub.
            $raw = (string) file_get_contents($path);
            if (str_contains($raw, self::UNINITIALIZED)) {
                return self::UNINITIALIZED;
            }

            throw new \RuntimeException("Unable to parse existing durin.yaml at {$path}");
        }
    }

    /**
     * Neutral app roots already own Composer identity and env templates.
     * Preset plans still describe those files for greenfield `durin new`;
     * init merges composer.json / .env.example instead of blind overwrite.
     */
    private function filterPlanForExistingRoot(string $root, ScaffoldPlan $plan): ScaffoldPlan
    {
        $preserve = [
            'composer.json' => true,
            'composer.lock' => true,
            '.env' => true,
            '.env.example' => true,
            'config/app.php' => true,
            'README.md' => true,
            'phpunit.xml' => true,
            '.gitignore' => true,
        ];

        $filtered = new ScaffoldPlan();
        foreach ($plan->actions() as $action) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $action->relativePath);
            if (isset($preserve[$action->relativePath]) && is_file($absolute)) {
                continue;
            }
            if ($action->type === ScaffoldActionType::CreateDirectory) {
                $filtered->directory($action->relativePath);
                continue;
            }
            $filtered->file($action->relativePath, (string) ($action->contents ?? ''));
        }

        return $filtered;
    }

    /**
     * Neutral stubs (empty routes, placeholder index) may differ from preset output.
     * Init replaces them; preserve-list files are never deleted.
     */
    private function clearReplaceableConflicts(string $root, ScaffoldPlan $plan): void
    {
        $preserve = [
            'composer.json' => true,
            'composer.lock' => true,
            '.env' => true,
            '.env.example' => true,
            'config/app.php' => true,
            'README.md' => true,
            'phpunit.xml' => true,
            '.gitignore' => true,
        ];

        foreach ($plan->actions() as $action) {
            if (isset($preserve[$action->relativePath])) {
                continue;
            }
            if ($action->type !== ScaffoldActionType::WriteFile) {
                continue;
            }
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $action->relativePath);
            if (!is_file($absolute)) {
                continue;
            }
            $existing = file_get_contents($absolute);
            if ($existing === ($action->contents ?? '')) {
                continue;
            }
            if (!@unlink($absolute)) {
                throw new \RuntimeException("Unable to replace stub file during init: {$action->relativePath}");
            }
        }
    }

    private function extractPlannedContents(ScaffoldPlan $plan, string $relativePath): ?string
    {
        foreach ($plan->actions() as $action) {
            if (
                $action->type === ScaffoldActionType::WriteFile
                && $action->relativePath === $relativePath
            ) {
                return (string) ($action->contents ?? '');
            }
        }

        return null;
    }

    private function applyMergedComposer(string $root, ?string $plannedJson, RuntimePlan $runtime): void
    {
        $path = $root . DIRECTORY_SEPARATOR . 'composer.json';
        $existingRaw = is_file($path) ? (string) file_get_contents($path) : '{}';
        $existing = json_decode($existingRaw, true);
        if (!is_array($existing)) {
            throw new \RuntimeException("Invalid composer.json at {$path}");
        }

        $planned = [];
        if ($plannedJson !== null && $plannedJson !== '') {
            $decoded = json_decode($plannedJson, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('Preset planned composer.json is invalid JSON.');
            }
            $planned = $decoded;
        }

        $merged = $this->composerMerger->merge($existing, $planned, $runtime);
        file_put_contents($path, $this->composerMerger->encode($merged));
    }

    private function applyMergedEnvExample(string $root, ?string $plannedEnv): void
    {
        if ($plannedEnv === null) {
            return;
        }

        $path = $root . DIRECTORY_SEPARATOR . '.env.example';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $merged = $this->envMerger->merge($existing, $plannedEnv);
        file_put_contents($path, $merged);
    }

    private function resolveApplicationName(string $root): string
    {
        $env = getenv('APP_NAME');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        $composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($composerPath)) {
            $data = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($data) && isset($data['name']) && is_string($data['name'])) {
                $parts = explode('/', $data['name']);

                return $parts[array_key_last($parts)] ?: basename($root);
            }
        }

        return basename($root);
    }

    private function assertNoVendorMutation(string $root): void
    {
        // Soft guard: ensure forge package path still exists as package, not overwritten as app.
        $forgeVendor = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
            . 'ereborcodeforge' . DIRECTORY_SEPARATOR . 'durins-forge';
        if (is_dir($forgeVendor) && !is_file($forgeVendor . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new \RuntimeException('Detected unexpected mutation under vendor/ereborcodeforge/durins-forge.');
        }
    }
}
