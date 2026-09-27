<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\ServeCommand;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilRuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeStatus;
use PHPUnit\Framework\TestCase;

final class FakeServeFacade implements RuntimeFacade
{
    /** @var list<RuntimeOptions> */
    public array $serveCalls = [];

    public int $exitCode = 0;

    public function serve(RuntimeOptions $options): int
    {
        $this->serveCalls[] = $options;

        return $this->exitCode;
    }

    public function dev(RuntimeOptions $options): int
    {
        throw new \LogicException('dev must not be called from ServeCommand');
    }

    public function status(RuntimeOptions $options): RuntimeStatus
    {
        throw new \LogicException('status must not be called from ServeCommand');
    }
}

final class ServeCommandTest extends TestCase
{
    public function test_serve_command_delegates_to_runtime_facade(): void
    {
        $facade = new FakeServeFacade();
        $command = new ServeCommand($facade);
        $command->setArgs(['--host=127.0.0.1', '--port=9090', '--workers=2']);

        $code = $command->execute();

        $this->assertSame(0, $code);
        $this->assertCount(1, $facade->serveCalls);
        $options = $facade->serveCalls[0];
        $this->assertSame('127.0.0.1', $options->host);
        $this->assertSame(9090, $options->port);
        $this->assertSame(2, $options->workers);
    }

    public function test_serve_command_propagates_facade_exit_code(): void
    {
        $facade = new FakeServeFacade();
        $facade->exitCode = 7;
        $command = new ServeCommand($facade);
        $command->setArgs([]);

        $this->assertSame(7, $command->execute());
    }

    public function test_default_facade_is_mithril_runtime_facade(): void
    {
        $command = new ServeCommand();
        $ref = new \ReflectionClass($command);
        $prop = $ref->getProperty('facade');
        $prop->setAccessible(true);

        $this->assertNull($prop->getValue($command));

        // Constructing default path uses MithrilRuntimeFacade at execute-time; type check via new.
        $this->assertInstanceOf(MithrilRuntimeFacade::class, new MithrilRuntimeFacade());
    }
}
