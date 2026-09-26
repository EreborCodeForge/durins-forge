<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tooling\Doctor\DoctorContext;
use App\Tooling\Doctor\DoctorRunner;
use App\Tooling\Doctor\JsonDoctorRenderer;
use App\Tooling\Doctor\TextDoctorRenderer;
use App\Tooling\Project\DurinManifestException;
use App\Tooling\Project\ProjectDiscovery;
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
