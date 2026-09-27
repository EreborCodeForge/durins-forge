<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console\Commands;

use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorContext;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\DoctorRunner;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\JsonDoctorRenderer;
use EreborCodeForge\Durin\Forge\Tooling\Doctor\TextDoctorRenderer;
use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use Erebor\Mithril\Console\ArgParser;
use Erebor\Mithril\Console\Command;

final class DoctorCommand extends Command
{
    public static function getSignature(): string
    {
        return 'doctor';
    }

    public static function getDescription(): string
    {
        return 'Diagnostica PHP, projeto, Mithril/Eregion e artefatos compilados';
    }

    public function execute(): int
    {
        $parsed = ArgParser::parse($this->args);
        $json = ($parsed['options']['json'] ?? false) === true;
        $strict = ($parsed['options']['strict'] ?? false) === true;

        try {
            $project = (new ProjectDiscovery())->discover(getcwd() ?: base_path());
        } catch (DurinManifestException $e) {
            $this->error($e->getMessage());

            return 2;
        }

        $report = (new DoctorRunner())->run(new DoctorContext($project, $strict));
        $renderer = $json ? new JsonDoctorRenderer() : new TextDoctorRenderer();
        echo $renderer->render($report);

        return $report->exitCode();
    }
}
