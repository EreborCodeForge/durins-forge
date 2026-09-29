<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProvisioner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeResolutionException;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeResolver;
use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use PHPUnit\Framework\TestCase;

final class RuntimeResolverTest extends TestCase
{
    private RuntimeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = RuntimeResolver::builtIn();
    }

    public function test_minimal_resolves_mithril_http_with_eregion(): void
    {
        $profile = (new DefaultPresetRegistryFactory())->create()->definition('minimal')->runtime();
        $plan = $this->resolver->resolve($profile);

        $this->assertSame('http', $plan->mode);
        $this->assertSame('mithril-http', $plan->executionRuntime);
        $this->assertSame('eregion', $plan->supervisor);
    }

    public function test_service_resolves_mithril_http_with_eregion(): void
    {
        $profile = (new DefaultPresetRegistryFactory())->create()->definition('service')->runtime();
        $plan = $this->resolver->resolve($profile);

        $this->assertSame('http', $plan->mode);
        $this->assertSame('mithril-http', $plan->executionRuntime);
        $this->assertSame('eregion', $plan->supervisor);
    }

    public function test_worker_resolves_mithril_job_without_supervisor(): void
    {
        $profile = (new DefaultPresetRegistryFactory())->create()->definition('worker')->runtime();
        $plan = $this->resolver->resolve($profile);

        $this->assertSame('job', $plan->mode);
        $this->assertSame('mithril-job', $plan->executionRuntime);
        $this->assertNull($plan->supervisor);
    }

    public function test_worker_does_not_install_eregion(): void
    {
        $profile = (new DefaultPresetRegistryFactory())->create()->definition('worker')->runtime();
        $plan = $this->resolver->resolve($profile);
        $provisioner = new RuntimeProvisioner(defaultInstallRunner: true);

        $this->assertFalse($provisioner->shouldInstall($profile, $plan));
    }

    public function test_unsupported_capability_fails_explicitly(): void
    {
        $this->expectException(RuntimeResolutionException::class);
        $this->expectExceptionMessage('No compatible execution runtime');

        $this->resolver->resolve(new RuntimeProfile(
            mode: 'http',
            requiredCapabilities: ['quantum-entanglement'],
        ));
    }

    public function test_preferred_compatible_execution(): void
    {
        $plan = $this->resolver->resolve(new RuntimeProfile(
            mode: 'worker',
            requiredCapabilities: ['messaging'],
            preferredRunner: 'mithril-job',
        ));

        $this->assertSame('mithril-job', $plan->executionRuntime);
        $this->assertNull($plan->supervisor);
    }

    public function test_preferred_incompatible_execution(): void
    {
        $this->expectException(RuntimeResolutionException::class);
        $this->expectExceptionMessage('does not satisfy required capabilities');

        $this->resolver->resolve(new RuntimeProfile(
            mode: 'http',
            preferredRunner: 'mithril-job',
        ));
    }

    public function test_job_with_eregion_supervisor(): void
    {
        $plan = $this->resolver->resolve(new RuntimeProfile(
            mode: 'worker',
            requiredCapabilities: ['messaging'],
            preferredRunner: 'eregion',
        ));

        $this->assertSame('mithril-job', $plan->executionRuntime);
        $this->assertSame('eregion', $plan->supervisor);
        $this->assertTrue($plan->usesEregion());
    }

    public function test_no_silent_eregion_fallback_when_preferred_runner_null_on_worker(): void
    {
        $profile = new RuntimeProfile(
            mode: 'worker',
            requiredCapabilities: ['messaging'],
        );
        $plan = $this->resolver->resolve($profile);

        $this->assertNull($plan->supervisor);
        $this->assertNotSame('eregion', (new RuntimeProvisioner())->resolveRunner($profile));
    }

    public function test_complete_payload_shape(): void
    {
        $plan = new RuntimePlan('job', 'mithril-job', null, ['job-loop', 'messaging']);

        $this->assertSame([
            'mode' => 'job',
            'execution' => 'mithril-job',
            'supervisor' => null,
        ], $plan->toCompletePayload());
    }
}
