<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Support;

use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationPathTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationPath::reset();
        parent::tearDown();
    }

    public function test_set_root_and_path(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_app_path_' . uniqid('', true);
        mkdir($root);

        try {
            ApplicationPath::setRoot($root);
            $this->assertSame(realpath($root) ?: $root, ApplicationPath::root());
            $this->assertSame(
                (realpath($root) ?: $root) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
                ApplicationPath::path('config/app.php')
            );
            $this->assertSame(ApplicationPath::path('x'), base_path('x'));
        } finally {
            rmdir($root);
        }
    }

    public function test_refuses_vendor_package_install_path(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin_vendor_root_' . uniqid('', true);
        $pkg = $tmp . '/vendor/ereborcodeforge/durins-forge';
        mkdir($pkg, 0777, true);
        file_put_contents($pkg . '/composer.json', '{"name":"ereborcodeforge/durins-forge"}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to use the Durin Forge package install path');

        try {
            ApplicationPath::discoverFromCwd($pkg);
        } finally {
            unlink($pkg . '/composer.json');
            // cleanup
            $parts = ['durins-forge', 'ereborcodeforge', 'vendor'];
            $dir = $pkg;
            foreach ($parts as $_) {
                @rmdir($dir);
                $dir = dirname($dir);
            }
            @rmdir($tmp);
        }
    }

    public function test_application_namespace_default(): void
    {
        $this->assertSame('App\\', ApplicationPath::applicationNamespace());
        ApplicationPath::setApplicationNamespace('MyApp');
        $this->assertSame('MyApp\\', ApplicationPath::applicationNamespace());
    }
}
