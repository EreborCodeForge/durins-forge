<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\StatusCommand;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeFacade;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeOptions;
use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimeStatus;
use PHPUnit\Framework\TestCase;

final class FakeStatusFacade implements RuntimeFacade
{
    public function __construct(
        private readonly RuntimeStatus $status,
    ) {}

    public function serve(RuntimeOptions $options): int
    {
        throw new \LogicException('serve must not be called');
    }

    public function dev(RuntimeOptions $options): int
    {
        throw new \LogicException('dev must not be called');
    }

    public function status(RuntimeOptions $options): RuntimeStatus
    {
        return $this->status;
    }
}

final class StatusCommandTest extends TestCase
{
    public function test_status_command_uses_facade_and_exits_zero_when_available(): void
    {
        $facade = new FakeStatusFacade(new RuntimeStatus(
            true,
            'ok',
            binaryPath: '/bin/eregion',
            details: ['http_host' => '0.0.0.0', 'http_port' => 8080, 'protocol' => 'eregion/1'],
        ));
        $command = new StatusCommand($facade);
        $command->setArgs([]);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Durin Runtime Status', $output);
        $this->assertStringContainsString('tooling available', $output);
    }

    public function test_status_json_mode(): void
    {
        $facade = new FakeStatusFacade(new RuntimeStatus(false, 'missing binary'));
        $command = new StatusCommand($facade);
        $command->setArgs(['--json']);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(1, $code);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data['available']);
    }

    public function test_watch_flag_is_rejected(): void
    {
        $facade = new FakeStatusFacade(new RuntimeStatus(true, 'ok'));
        $command = new StatusCommand($facade);
        $command->setArgs(['--watch']);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--watch is not implemented', $output);
    }
}
