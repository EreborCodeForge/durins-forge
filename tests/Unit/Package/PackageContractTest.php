<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Package;

use EreborCodeForge\Durin\Forge\Core\Http\HttpApplicationKernel;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use PHPUnit\Framework\TestCase;

/**
 * Composer / public-surface contract for ereborcodeforge/durins-forge.
 */
final class PackageContractTest extends TestCase
{
    public function test_composer_json_matches_package_contract(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('ereborcodeforge/durins-forge', $composer['name']);
        $this->assertSame('library', $composer['type']);
        $this->assertSame('^8.5', $composer['require']['php']);
        $this->assertSame(
            ['EreborCodeForge\\Durin\\Forge\\' => 'src/'],
            $composer['autoload']['psr-4']
        );
        $this->assertContains('src/Support/helpers.php', $composer['autoload']['files']);
        $this->assertContains('bin/durin', $composer['bin']);
        $this->assertContains('bin/durins-forge', $composer['bin']);

        $this->assertArrayNotHasKey('repositories', $composer);

        foreach ([
            'ereborcodeforge/durin-core',
            'ereborcodeforge/durin-presets',
            'ereborcodeforge/durin-architecture',
            'ereborcodeforge/mithrilphp',
            'ereborcodeforge/mazarbul',
        ] as $package) {
            $this->assertArrayHasKey($package, $composer['require']);
        }
    }

    public function test_public_php_surface_classes_exist(): void
    {
        $this->assertTrue(class_exists(ApplicationPath::class));
        $this->assertTrue(class_exists(HttpApplicationKernel::class));
        $this->assertTrue(function_exists('base_path'));
        $this->assertTrue(function_exists('db'));
    }
}
