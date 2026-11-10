<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;
use Erebor\Mithril\Runtime\Guard\GuardConfig;
use Erebor\Mithril\Runtime\Guard\GuardConfigException;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\GuardReportWriter;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\ScenarioPathGuard;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\ScenarioRunner;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\TextGuardReportRenderer;
use EreborCodeForge\Durin\Forge\Tooling\WorkerGuard\WorkerAuditException;

final class WorkerAuditCommand extends Command
{
    public static function getSignature(): string
    {
        return 'worker:audit';
    }

    public static function getDescription(): string
    {
        return 'Audita estado residual de worker em um cenário local, sem subir o Eregion';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $scenario = ArgParser::string($parsed['options'], 'scenario');
        $format = ArgParser::string($parsed['options'], 'format', 'text') ?? 'text';
        $strict = ($parsed['options']['strict'] ?? false) === true;

        if ($scenario === null || $scenario === '') {
            $this->error('Missing --scenario');

            return 2;
        }
        if ($format !== 'text' && $format !== 'json') {
            $this->error('Invalid --format');

            return 2;
        }

        try {
            $project = (new ProjectDiscovery())->discover(getcwd() ?: null);
            $configPath = $project->root() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'worker_guard.php';
            if (is_file($configPath)) {
                GuardConfig::loadFile($configPath, false);
            }

            $loaded = (new ScenarioPathGuard())->load($project->root(), $scenario);
            $report = (new ScenarioRunner())->run($loaded);
            (new GuardReportWriter())->write($project->root(), $report);
        } catch (DurinManifestException) {
            $this->error('Unable to locate the project');

            return 2;
        } catch (GuardConfigException) {
            $this->error('Invalid worker guard config');

            return 2;
        } catch (WorkerAuditException) {
            $this->error('Worker audit failed');

            return 2;
        }

        if ($format === 'json') {
            echo json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            echo (new TextGuardReportRenderer())->render($report);
        }

        return $report->auditExitCode($strict);
    }
}
