<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\ConfigCacheCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\ConfigClearCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\ContainerClearCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\ContainerCompileCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\DevCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\DoctorCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\GraphDependenciesCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MigrateCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MigrateFreshCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MigrateRollbackCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MakeFeatureCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MakeModuleCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\MakeUseCaseCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\NewCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\OptimizeCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\RoutesClearCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\RoutesCompileCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\RoutesPostmanCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\SeedCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\ServeCommand;
use EreborCodeForge\Durin\Forge\Console\Commands\StatusCommand;
use Erebor\Mithril\Console\Kernel as MithrilKernel;

class Application
{
    public function run(array $argv): int
    {
        $kernel = new MithrilKernel();
        $kernel->register(NewCommand::class);
        $kernel->register(MigrateCommand::class);
        $kernel->register(MigrateRollbackCommand::class);
        $kernel->register(MigrateFreshCommand::class);
        $kernel->register(SeedCommand::class);
        $kernel->register(MakeModuleCommand::class);
        $kernel->register(MakeUseCaseCommand::class);
        $kernel->register(MakeFeatureCommand::class);
        $kernel->register(RoutesPostmanCommand::class);
        $kernel->register(RoutesCompileCommand::class);
        $kernel->register(RoutesClearCommand::class);
        $kernel->register(ConfigCacheCommand::class);
        $kernel->register(ConfigClearCommand::class);
        $kernel->register(ContainerCompileCommand::class);
        $kernel->register(ContainerClearCommand::class);
        $kernel->register(OptimizeCommand::class);
        $kernel->register(ServeCommand::class);
        $kernel->register(DevCommand::class);
        $kernel->register(StatusCommand::class);
        $kernel->register(DoctorCommand::class);
        $kernel->register(GraphDependenciesCommand::class);
        return $kernel->handle($argv);
    }
}
