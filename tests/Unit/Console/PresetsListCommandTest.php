<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use EreborCodeForge\Durin\Forge\Console\Commands\PresetsListCommand;
use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use PHPUnit\Framework\TestCase;

final class PresetsListCommandTest extends TestCase
{
    public function test_list_uses_registry_metadata(): void
    {
        $command = new PresetsListCommand((new DefaultPresetRegistryFactory())->create());
        $command->setArgs([]);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Available Durin presets', $output);
        $this->assertStringContainsString('minimal', $output);
        $this->assertStringContainsString('service', $output);
        $this->assertStringContainsString('worker', $output);
        $this->assertStringContainsString('Default: minimal', $output);
    }

    public function test_list_json_format_comes_from_catalog(): void
    {
        $command = new PresetsListCommand((new DefaultPresetRegistryFactory())->create());
        $command->setArgs(['--format=json']);

        ob_start();
        $code = $command->execute();
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('minimal', $data['default']);
        $this->assertCount(3, $data['presets']);
        $this->assertArrayHasKey('runtime', $data['presets'][0]);
    }
}
