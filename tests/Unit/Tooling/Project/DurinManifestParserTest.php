<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Project;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestParser;
use EreborCodeForge\Durin\Core\Runtime\ResolvedRuntime;
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
        $this->assertNotNull($manifest->runtime);
        $this->assertSame('mithril', $manifest->runtime->engine);
        $this->assertSame('eregion', $manifest->runtime->supervisor);
        $this->assertSame('http', $manifest->runtime->mode);
        $this->assertFalse($manifest->isJobMode());
    }

    public function test_parses_job_mode_worker_manifest(): void
    {
        $manifest = (new DurinManifestParser())->parse(<<<'YAML'
application:
  name: notifications
  preset: worker
runtime:
  mode: job
  engine: mithril
  execution: mithril-job
features:
  http: false
  messaging: true
architecture:
  modules: false
YAML);

        $this->assertTrue($manifest->isJobMode());
        $this->assertNotNull($manifest->runtime);
        $this->assertSame('job', $manifest->runtime->mode);
        $this->assertSame('mithril-job', $manifest->runtime->execution);
        $this->assertNull($manifest->runtime->supervisor);
    }

    public function test_parses_unresolved_runtime(): void
    {
        $manifest = (new DurinManifestParser())->parse(<<<'YAML'
application:
  name: bare
  preset: uninitialized
runtime:
  state: unresolved
features:
  http: false
  messaging: false
architecture:
  modules: false
YAML);

        $this->assertNull($manifest->runtime);
        $this->assertFalse($manifest->isResolved());
    }

    public function test_round_trip_yaml(): void
    {
        $original = new DurinManifest(
            applicationName: 'catalog',
            preset: 'minimal',
            features: ['http' => true, 'messaging' => false],
            architecture: ['modules' => false],
            runtime: new ResolvedRuntime(
                mode: 'http',
                engine: 'mithril',
                execution: 'mithril-http',
                supervisor: 'eregion',
            ),
        );

        $parsed = (new DurinManifestParser())->parse($original->toYaml());

        $this->assertSame($original->toArray(), $parsed->toArray());
    }

    public function test_rejects_invalid_application_section(): void
    {
        $this->expectException(DurinManifestException::class);
        (new DurinManifestParser())->parse(<<<'YAML'
runtime:
  engine: mithril
YAML);
    }

    public function test_defaults_features_when_absent(): void
    {
        $manifest = (new DurinManifestParser())->parse(<<<'YAML'
application:
  name: bare
  preset: minimal
runtime:
  state: unresolved
YAML);

        $this->assertFalse($manifest->features['http']);
        $this->assertFalse($manifest->features['messaging']);
    }
}
