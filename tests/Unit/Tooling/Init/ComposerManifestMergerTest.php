<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Init;

use EreborCodeForge\Durin\Forge\Tooling\Init\ComposerManifestMerger;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;
use PHPUnit\Framework\TestCase;

final class ComposerManifestMergerTest extends TestCase
{
    public function test_preserves_identity_and_applies_http_mithril_extra(): void
    {
        $existing = [
            'name' => 'acme/billing',
            'description' => 'Billing root',
            'require' => [
                'php' => '^8.5',
                'ereborcodeforge/durins-forge' => '^0.2.3',
            ],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ];
        $planned = [
            'name' => 'acme/from-preset',
            'require' => [
                'php' => '^8.5',
                'ereborcodeforge/durins-forge' => '^0.1',
            ],
            'extra' => [
                'mithril' => [
                    'kernel' => 'App\\Kernel',
                    'eregion' => 'v0.4.0',
                    'eregion_repo' => 'EreborCodeForge/eregion',
                ],
            ],
            'scripts' => ['test' => 'phpunit'],
        ];

        $merged = (new ComposerManifestMerger())->merge(
            $existing,
            $planned,
            new RuntimePlan('http', 'mithril-http', 'eregion', ['persistent-http']),
        );

        $this->assertSame('acme/billing', $merged['name']);
        $this->assertSame('Billing root', $merged['description']);
        $this->assertSame(['psr-4' => ['App\\' => 'src/']], $merged['autoload']);
        $this->assertSame('^0.1', $merged['require']['ereborcodeforge/durins-forge']);
        $this->assertSame('App\\Kernel', $merged['extra']['mithril']['kernel']);
        $this->assertSame('v0.4.0', $merged['extra']['mithril']['eregion']);
        $this->assertArrayNotHasKey('job_kernel', $merged['extra']['mithril']);
        $this->assertSame('phpunit', $merged['scripts']['test']);
    }

    public function test_upgrades_eregion_pin_below_baseline(): void
    {
        $merged = (new ComposerManifestMerger())->merge(
            ['name' => 'acme/app'],
            [
                'extra' => [
                    'mithril' => [
                        'kernel' => 'App\\Kernel',
                        'eregion' => 'v0.3.0',
                        'eregion_repo' => 'EreborCodeForge/eregion',
                    ],
                ],
            ],
            new RuntimePlan('http', 'mithril-http', 'eregion', ['persistent-http']),
        );

        $this->assertSame('v0.4.0', $merged['extra']['mithril']['eregion']);
    }

    public function test_job_runtime_strips_eregion_and_http_kernel(): void
    {
        $existing = [
            'name' => 'acme/jobs',
            'extra' => [
                'mithril' => [
                    'kernel' => 'App\\Kernel',
                    'eregion' => 'v0.4.0',
                    'eregion_repo' => 'EreborCodeForge/eregion',
                ],
            ],
        ];
        $planned = [
            'extra' => [
                'mithril' => [
                    'job_kernel' => 'App\\JobKernel',
                ],
            ],
            'scripts' => ['job:work' => 'job-worker'],
        ];

        $merged = (new ComposerManifestMerger())->merge(
            $existing,
            $planned,
            new RuntimePlan('job', 'mithril-job', null, ['job-loop', 'messaging']),
        );

        $this->assertSame('acme/jobs', $merged['name']);
        $this->assertSame('App\\JobKernel', $merged['extra']['mithril']['job_kernel']);
        $this->assertArrayNotHasKey('kernel', $merged['extra']['mithril']);
        $this->assertArrayNotHasKey('eregion', $merged['extra']['mithril']);
        $this->assertArrayNotHasKey('eregion_repo', $merged['extra']['mithril']);
        $this->assertSame('job-worker', $merged['scripts']['job:work']);
    }

    public function test_supervised_job_keeps_eregion_pin_and_job_kernel(): void
    {
        $merged = (new ComposerManifestMerger())->merge(
            [
                'name' => 'acme/jobs',
                'extra' => [
                    'mithril' => [
                        'kernel' => 'App\\Kernel',
                    ],
                ],
            ],
            [
                'extra' => [
                    'mithril' => [
                        'job_kernel' => 'App\\JobKernel',
                    ],
                ],
            ],
            new RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'process-supervision']),
        );

        $this->assertSame('App\\JobKernel', $merged['extra']['mithril']['job_kernel']);
        $this->assertArrayNotHasKey('kernel', $merged['extra']['mithril']);
        $this->assertSame('v0.4.0', $merged['extra']['mithril']['eregion']);
        $this->assertSame('EreborCodeForge/eregion', $merged['extra']['mithril']['eregion_repo']);
    }
}
