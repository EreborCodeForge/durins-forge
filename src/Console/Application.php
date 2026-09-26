<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\ConfigCacheCommand;
use App\Console\Commands\ConfigClearCommand;
use App\Console\Commands\ContainerClearCommand;
use App\Console\Commands\ContainerCompileCommand;
use App\Console\Commands\DevCommand;
use App\Console\Commands\DoctorCommand;
use App\Console\Commands\GraphDependenciesCommand;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\MigrateFreshCommand;
use App\Console\Commands\MigrateRollbackCommand;
use App\Console\Commands\MakeFeatureCommand;
use App\Console\Commands\MakeModuleCommand;
use App\Console\Commands\MakeUseCaseCommand;
use App\Console\Commands\NewCommand;
use App\Console\Commands\OptimizeCommand;
use App\Console\Commands\RoutesClearCommand;
use App\Console\Commands\RoutesCompileCommand;
use App\Console\Commands\RoutesPostmanCommand;
use App\Console\Commands\SeedCommand;
use App\Console\Commands\ServeCommand;
use App\Console\Commands\StatusCommand;
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
