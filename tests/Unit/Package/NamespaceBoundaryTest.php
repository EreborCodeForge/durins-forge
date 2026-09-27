<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Package;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Production Forge sources must not claim the consumer App\ namespace.
 */
final class NamespaceBoundaryTest extends TestCase
{
    public function test_src_php_files_do_not_declare_app_namespace(): void
    {
        $src = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match('/^namespace\s+App(?:\\\\|;)/m', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'Production src/ must not declare namespace App');
    }

    public function test_composer_autoload_is_forge_namespace(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame('library', $composer['type']);
        $this->assertArrayHasKey('EreborCodeForge\\Durin\\Forge\\', $composer['autoload']['psr-4']);
        $this->assertArrayNotHasKey('App\\', $composer['autoload']['psr-4'] ?? []);
    }
}
