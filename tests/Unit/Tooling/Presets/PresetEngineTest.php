<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Presets;

use EreborCodeForge\Durin\Presets\Preset\ManifestPlanFactory;
use EreborCodeForge\Durin\Core\Contract\Preset;
use EreborCodeForge\Durin\Presets\Registry\PresetEngine;
use EreborCodeForge\Durin\Presets\Registry\PresetRegistry;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Presets\Preset\UnknownPresetException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldActionType;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use PHPUnit\Framework\TestCase;

final class FakePreset implements Preset
{
    public function __construct(
        private readonly string $presetName = 'fake',
        private readonly bool $includeManifest = false,
    ) {}

    public function name(): string
    {
        return $this->presetName;
    }

    public function scaffold(ProjectOptions $options): ScaffoldPlan
    {
        $plan = (new ScaffoldPlan())
            ->directory('src')
            ->file('src/.gitkeep', '');

        if ($this->includeManifest) {
            (new ManifestPlanFactory())->appendManifest($plan, $options);
        }

        return $plan;
    }
}

final class PresetEngineTest extends TestCase
{
    public function test_registry_resolves_registered_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->register(new FakePreset('fake'));

        $this->assertTrue($registry->has('fake'));
        $this->assertSame(['fake'], $registry->names());
        $this->assertSame('fake', $registry->get('fake')->name());
    }

    public function test_registry_rejects_unknown_preset(): void
    {
        $registry = new PresetRegistry();
        $registry->register(new FakePreset('fake'));

        $this->expectException(UnknownPresetException::class);
        $this->expectExceptionMessage('Unknown preset "missing"');

        $registry->get('missing');
    }

    public function test_engine_builds_plan_without_writing_and_appends_manifest(): void
    {
        $registry = new PresetRegistry();
        $registry->register(new FakePreset('fake'));
        $engine = new PresetEngine($registry);

        $options = new ProjectOptions(
            name: 'demo',
            preset: 'fake',
            targetDirectory: '/tmp/demo',
        );

        $plan = $engine->plan($options);

        $paths = array_map(static fn ($a) => $a->relativePath, $plan->actions());
        $this->assertContains('src', $paths);
        $this->assertContains('src/.gitkeep', $paths);
        $this->assertContains('durin.yaml', $paths);

        $yamlAction = null;
        foreach ($plan->actions() as $action) {
            if ($action->relativePath === 'durin.yaml') {
                $yamlAction = $action;
                break;
            }
        }

        $this->assertNotNull($yamlAction);
        $this->assertSame(ScaffoldActionType::WriteFile, $yamlAction->type);
        $manifest = (new DurinManifestParser())->parse((string) $yamlAction->contents);
        $this->assertSame('demo', $manifest->applicationName);
        $this->assertSame('fake', $manifest->preset);
    }

    public function test_engine_does_not_duplicate_manifest_when_preset_already_plans_it(): void
    {
        $registry = new PresetRegistry();
        $registry->register(new FakePreset('fake', includeManifest: true));
        $engine = new PresetEngine($registry);

        $plan = $engine->plan(new ProjectOptions('demo', 'fake', '/tmp/demo'));
        $durinCount = 0;
        foreach ($plan->actions() as $action) {
            if ($action->relativePath === 'durin.yaml') {
                $durinCount++;
            }
        }

        $this->assertSame(1, $durinCount);
    }

    public function test_manifest_factory_round_trips_options(): void
    {
        $options = new ProjectOptions(
            name: 'billing',
            preset: 'service',
            targetDirectory: '/tmp/billing',
            modules: true,
        );

        $manifest = (new ManifestPlanFactory())->forOptions($options);
        $this->assertSame('billing', $manifest->applicationName);
        $this->assertTrue($manifest->architecture['modules']);
    }
}
