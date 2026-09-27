<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Project;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use PHPUnit\Framework\TestCase;

final class DurinManifestParserTest extends TestCase
{
    public function test_parses_minimal_manifest(): void
    {
        $yaml = <<<'YAML'
# durin.yaml
application:
  name: billing
  preset: service

runtime:
  engine: mithril
  server: eregion

features:
  http: true
  messaging: false

architecture:
  modules: false
YAML;

        $manifest = (new DurinManifestParser())->parse($yaml);

        $this->assertSame('billing', $manifest->applicationName);
        $this->assertSame('service', $manifest->preset);
        $this->assertSame('mithril', $manifest->runtimeEngine);
        $this->assertSame('eregion', $manifest->runtimeServer);
        $this->assertSame('http', $manifest->runtimeMode);
        $this->assertFalse($manifest->isJobMode());
    }

    public function test_parses_job_mode_worker_manifest(): void
    {
        $manifest = (new DurinManifestParser())->parse(<<<'YAML'
application:
  name: notifications
  preset: worker
runtime:
  engine: mithril
  server: none
  mode: job
features:
  http: false
  messaging: true
architecture:
  modules: false
YAML);

        $this->assertTrue($manifest->isJobMode());
        $this->assertSame('job', $manifest->runtimeMode);
        $this->assertSame('none', $manifest->runtimeServer);
    }

    public function test_round_trip_yaml(): void
    {
        $original = new DurinManifest(
            applicationName: 'catalog',
            preset: 'minimal',
            runtimeEngine: 'mithril',
            runtimeServer: 'eregion',
            features: ['http' => true, 'messaging' => false],
            architecture: ['modules' => false],
        );

        $parsed = (new DurinManifestParser())->parse($original->toYaml());

        $this->assertSame($original->toArray(), $parsed->toArray());
    }

    public function test_rejects_missing_application_name(): void
    {
        $this->expectException(DurinManifestException::class);

        (new DurinManifestParser())->parse(<<<'YAML'
application:
  preset: minimal
YAML);
    }

    public function test_applies_runtime_defaults(): void
    {
        $manifest = (new DurinManifestParser())->parse(<<<'YAML'
application:
  name: api
  preset: minimal
YAML);

        $this->assertSame('mithril', $manifest->runtimeEngine);
        $this->assertSame('eregion', $manifest->runtimeServer);
        $this->assertSame('http', $manifest->runtimeMode);
    }
}
