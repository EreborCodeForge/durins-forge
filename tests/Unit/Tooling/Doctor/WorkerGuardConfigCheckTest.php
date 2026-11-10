<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Doctor;

use EreborCodeForge\Durin\Core\Manifest\DurinManifest;
use EreborCodeForge\Durin\Core\Project\Project;
use EreborCodeForge\Durin\Core\Project\ProjectPaths;
use EreborCodeForge\Durin\Core\Runtime\ResolvedRuntime;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\CheckStatus;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorReport;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\Checks\WorkerGuardConfigCheck;
use PHPUnit\Framework\TestCase;

final class WorkerGuardConfigCheckTest extends TestCase
{
    public function testMissingConfigStaysOff(): void
    {
        $results = (new WorkerGuardConfigCheck())->run($this->context($this->root()));

        $this->assertSame(CheckStatus::Ok, $results[0]->status);
        $this->assertStringContainsString('off', $results[0]->detail);
    }

    public function testInvalidConfigWarnsWithoutTheSecretAndStrictFails(): void
    {
        $root = $this->root();
        mkdir($root . '/config');
        file_put_contents(
            $root . '/config/worker_guard.php',
            "<?php\nreturn ['mode' => 'observe', 'secret' => 'synthetic-secret-token'];\n",
        );

        $results = (new WorkerGuardConfigCheck())->run($this->context($root));
        $this->assertSame(CheckStatus::Warning, $results[0]->status);
        $this->assertStringContainsString('durin doctor --strict', $results[0]->detail);
        $this->assertStringNotContainsString('synthetic-secret-token', $results[0]->detail);

        $relaxed = new DoctorReport($results, false);
        $strict = new DoctorReport($results, true);
        $this->assertSame(0, $relaxed->exitCode());
        $this->assertNotSame(0, $strict->exitCode());
    }

    public function testAuditModeWarnsForHttpWorker(): void
    {
        $root = $this->root();
        mkdir($root . '/config');
        file_put_contents($root . '/config/worker_guard.php', "<?php\nreturn ['mode' => 'audit'];\n");

        $results = (new WorkerGuardConfigCheck())->run($this->context($root));

        $this->assertSame(CheckStatus::Warning, $results[0]->status);
        $this->assertStringContainsString('HTTP', $results[0]->detail);
        $this->assertNotSame(0, (new DoctorReport($results, true))->exitCode());
    }

    public function testObserveModeIsOk(): void
    {
        $root = $this->root();
        mkdir($root . '/config');
        file_put_contents($root . '/config/worker_guard.php', "<?php\nreturn ['mode' => 'observe'];\n");

        $results = (new WorkerGuardConfigCheck())->run($this->context($root));

        $this->assertSame(CheckStatus::Ok, $results[0]->status);
        $this->assertStringContainsString('observe', $results[0]->detail);
    }

    private function root(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-wg11-' . bin2hex(random_bytes(4));
        mkdir($root);

        return $root;
    }

    private function context(string $root): DoctorContext
    {
        $manifest = new DurinManifest(
            applicationName: 'guard',
            preset: 'web',
            features: ['http' => true, 'messaging' => false],
            architecture: ['modules' => false],
            runtime: new ResolvedRuntime(
                mode: 'http',
                engine: 'mithril',
                execution: 'mithril-web',
                supervisor: 'eregion',
            ),
        );

        return new DoctorContext(new Project(new ProjectPaths($root), $manifest));
    }
}
