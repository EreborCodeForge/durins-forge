<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\MithrilRuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeMode;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeProcessRunner;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeStatus;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeStatusProvider;
use PHPUnit\Framework\TestCase;

final class RecordingProcessRunner implements RuntimeProcessRunner
{
    /** @var list<array{binary: string, arguments: list<string>, cwd: string}> */
    public array $calls = [];

    public int $exitCode = 0;

    public function run(string $binary, array $arguments, string $workingDirectory): int
    {
        $this->calls[] = [
            'binary' => $binary,
            'arguments' => $arguments,
            'cwd' => $workingDirectory,
        ];

        return $this->exitCode;
    }
}

final class StubStatusProvider implements RuntimeStatusProvider
{
    public function __construct(
        private readonly RuntimeStatus $status = new RuntimeStatus(true, 'stub'),
    ) {}

    public function read(RuntimeOptions $options): RuntimeStatus
    {
        return $this->status;
    }
}

final class RuntimeFacadeTest extends TestCase
{
    public function test_options_defaults_for_serve_and_dev(): void
    {
        $serve = RuntimeOptions::fromArgv([], '/app', RuntimeMode::Serve);
        $dev = RuntimeOptions::fromArgv([], '/app', RuntimeMode::Dev);

        $this->assertSame('0.0.0.0', $serve->host);
        $this->assertSame(8080, $serve->port);
        $this->assertSame('127.0.0.1', $dev->host);
        $this->assertSame(8080, $dev->port);
        $this->assertSame('development', $dev->environment);
        $this->assertFalse($dev->preferPhpServer);
    }

    public function test_options_parse_flags(): void
    {
        $options = RuntimeOptions::fromArgv([
            '--host=10.0.0.1',
            '--port=9090',
            '--workers=4',
            '--env=production',
            '--extra',
        ], '/app');

        $this->assertSame('10.0.0.1', $options->host);
        $this->assertSame(9090, $options->port);
        $this->assertSame(4, $options->workers);
        $this->assertSame('production', $options->environment);
        $this->assertSame(['--extra'], $options->passthroughArgs);
        $this->assertContains('--workers=4', $options->forgeServeArgs());
    }

    public function test_facade_serve_and_dev_share_process_runner(): void
    {
        $runner = new RecordingProcessRunner();
        $facade = new MithrilRuntimeFacade(
            processRunner: $runner,
            statusProvider: new StubStatusProvider(),
            forgeBinary: '/tmp/fake-forge',
        );

        $this->assertInstanceOf(RuntimeFacade::class, $facade);

        $cwd = sys_get_temp_dir();
        $serveCode = $facade->serve(new RuntimeOptions($cwd, host: '0.0.0.0', port: 8080));
        $devCode = $facade->dev(new RuntimeOptions($cwd, host: '127.0.0.1', port: 8080, mode: RuntimeMode::Dev));
        $phpDevCode = $facade->dev(new RuntimeOptions(
            $cwd,
            host: '127.0.0.1',
            port: 8000,
            mode: RuntimeMode::Dev,
            preferPhpServer: true,
        ));

        $this->assertSame(0, $serveCode);
        $this->assertSame(0, $devCode);
        $this->assertSame(0, $phpDevCode);
        $this->assertCount(3, $runner->calls);
        $this->assertSame(['serve', '--host=0.0.0.0', '--port=8080'], $runner->calls[0]['arguments']);
        $this->assertSame(['serve', '--host=127.0.0.1', '--port=8080'], $runner->calls[1]['arguments']);
        $this->assertSame(['serve:php', '--host=127.0.0.1', '--port=8000'], $runner->calls[2]['arguments']);
        $this->assertSame('/tmp/fake-forge', $runner->calls[0]['binary']);
        $this->assertSame('/tmp/fake-forge', $runner->calls[1]['binary']);
    }

    public function test_facade_status_delegates_to_provider(): void
    {
        $expected = new RuntimeStatus(true, 'ok', binaryPath: '/bin/eregion');
        $facade = new MithrilRuntimeFacade(
            processRunner: new RecordingProcessRunner(),
            statusProvider: new StubStatusProvider($expected),
            forgeBinary: '/tmp/fake-forge',
        );

        $status = $facade->status(new RuntimeOptions(sys_get_temp_dir()));

        $this->assertSame($expected, $status);
        $this->assertTrue($status->toArray()['available']);
    }
}
