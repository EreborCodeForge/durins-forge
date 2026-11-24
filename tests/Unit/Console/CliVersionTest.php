<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Console;

use Composer\InstalledVersions;
use EreborCodeForge\Durin\Forge\Console\Application;
use PHPUnit\Framework\TestCase;

final class CliVersionTest extends TestCase
{
    public function test_version_flag_prints_the_installed_package_version(): void
    {
        $expected = InstalledVersions::getPrettyVersion('ereborcodeforge/durins-forge');
        self::assertIsString($expected);
        self::assertNotSame('', $expected);

        $app = new Application();
        ob_start();
        $code = $app->run(['durins-forge', '--version']);
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertSame('durins-forge ' . $expected . PHP_EOL, $output);
        self::assertStringNotContainsString('Durin Installer', $output);
    }

    public function test_help_flag_belongs_to_the_local_command_list(): void
    {
        $app = new Application();
        ob_start();
        $code = $app->run(['durins-forge', '--help']);
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('doctor', $output);
        self::assertStringContainsString('presets:list', $output);
        self::assertStringNotContainsString('Durin Installer', $output);
    }
}
