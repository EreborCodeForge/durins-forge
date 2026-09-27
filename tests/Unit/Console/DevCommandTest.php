<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\DevCommand;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\DevRuntimeSummary;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeStatus;
use PHPUnit\Framework\TestCase;

final class FakeDevFacade implements RuntimeFacade
{
    /** @var list<RuntimeOptions> */
    public array $devCalls = [];

    public int $exitCode = 0;

    public function serve(RuntimeOptions $options): int
    {
        throw new \LogicException('serve must not be called from DevCommand');
    }

    public function dev(RuntimeOptions $options): int
    {
        $this->devCalls[] = $options;

        return $this->exitCode;
    }

    public function status(RuntimeOptions $options): RuntimeStatus
    {
        throw new \LogicException('status must not be called from DevCommand');
    }
}

final class DevCommandTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_dev_' . uniqid('', true);
        mkdir($this->tempRoot, 0777, true);
        file_put_contents($this->tempRoot . '/composer.json', '{"name":"demo/app"}');
        file_put_contents($this->tempRoot . '/.env', "APP_ENV=development\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->tempRoot . '/.env');
        @unlink($this->tempRoot . '/composer.json');
        @rmdir($this->tempRoot);
        parent::tearDown();
    }

    public function test_dev_command_prints_summary_and_delegates_to_facade(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $facade = new FakeDevFacade();
            $command = new DevCommand($facade, new DevRuntimeSummary());
            $command->setArgs(['--host=127.0.0.1', '--port=8080']);

            ob_start();
            $code = $command->execute();
            $output = (string) ob_get_clean();

            $this->assertSame(0, $code);
            $this->assertStringContainsString("Durin's Forge — development", $output);
            $this->assertStringContainsString('http://127.0.0.1:8080', $output);
            $this->assertCount(1, $facade->devCalls);
            $this->assertFalse($facade->devCalls[0]->preferPhpServer);
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_dev_command_honors_php_flag(): void
    {
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $facade = new FakeDevFacade();
            $command = new DevCommand($facade, new DevRuntimeSummary());
            $command->setArgs(['--php']);

            ob_start();
            $command->execute();
            ob_end_clean();

            $this->assertTrue($facade->devCalls[0]->preferPhpServer);
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }

    public function test_dev_command_requires_env_file(): void
    {
        unlink($this->tempRoot . '/.env');
        $cwd = getcwd();
        chdir($this->tempRoot);

        try {
            $facade = new FakeDevFacade();
            $command = new DevCommand($facade, new DevRuntimeSummary());
            $command->setArgs([]);

            ob_start();
            $code = $command->execute();
            ob_end_clean();

            $this->assertSame(2, $code);
            $this->assertSame([], $facade->devCalls);
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
    }
}
