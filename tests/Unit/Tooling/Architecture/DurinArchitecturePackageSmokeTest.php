<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Architecture;

use EreborCodeForge\Durin\Architecture\Adoption\AdoptionPlanner;
use EreborCodeForge\Durin\Architecture\Detection\ArchitectureDetector;
use EreborCodeForge\Durin\Architecture\Drift\DriftDetector;
use EreborCodeForge\Durin\Architecture\Drift\DriftFinding;
use EreborCodeForge\Durin\Architecture\Drift\DriftReport;
use EreborCodeForge\Durin\Architecture\Drift\Severity;
use EreborCodeForge\Durin\Architecture\Evolution\EvolutionPlanner;
use EreborCodeForge\Durin\Architecture\Migration\MigrationPlanner;
use EreborCodeForge\Durin\Architecture\Model\ArchitectureState;
use EreborCodeForge\Durin\Architecture\Model\ArchitectureTarget;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use EreborCodeForge\Durin\Core\Scaffold\ScaffoldPlan;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Vendor-install smoke for durin-architecture (no Forge architecture CLI yet).
 */
final class DurinArchitecturePackageSmokeTest extends TestCase
{
    public function test_package_contracts_are_autoloadable(): void
    {
        $this->assertTrue(interface_exists(ArchitectureDetector::class));
        $this->assertTrue(interface_exists(DriftDetector::class));
        $this->assertTrue(interface_exists(AdoptionPlanner::class));
        $this->assertTrue(interface_exists(EvolutionPlanner::class));
        $this->assertTrue(interface_exists(MigrationPlanner::class));
    }

    public function test_model_dtos_instantiate(): void
    {
        $state = new ArchitectureState(preset: 'minimal', applicationName: 'demo');
        $target = new ArchitectureTarget(preset: 'service');

        $this->assertSame('minimal', $state->preset);
        $this->assertSame('service', $target->preset);
    }

    public function test_drift_report_structure(): void
    {
        $report = new DriftReport([
            new DriftFinding(
                rule: 'layering',
                location: 'src/Http/Controller.php',
                message: 'Controller talks to infrastructure directly',
                severity: Severity::Warning,
            ),
        ]);

        $this->assertFalse($report->isClean());
    }

    public function test_presets_dependency_resolves_from_vendor(): void
    {
        $registry = (new DefaultPresetRegistryFactory())->create();
        $this->assertTrue($registry->has('minimal'));
        $this->assertInstanceOf(
            ScaffoldPlan::class,
            $registry->get('minimal')->scaffold(new ProjectOptions('demo', 'minimal', '/tmp/demo')),
        );
    }
}
