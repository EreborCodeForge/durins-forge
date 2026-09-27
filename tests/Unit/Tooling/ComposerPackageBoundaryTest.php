<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tooling;

use PHPUnit\Framework\TestCase;

/**
 * Ensures Forge production code does not reintroduce package-owned namespaces.
 */
final class ComposerPackageBoundaryTest extends TestCase
{
    public function test_forge_does_not_ship_duplicate_core_or_preset_trees(): void
    {
        $root = dirname(__DIR__, 3);

        $this->assertDirectoryDoesNotExist($root . '/src/Tooling/Project');
        $this->assertDirectoryDoesNotExist($root . '/src/Tooling/Scaffold');
        $this->assertDirectoryDoesNotExist($root . '/src/Tooling/Output');
        $this->assertDirectoryDoesNotExist($root . '/src/Tooling/Presets');
    }

    public function test_required_packages_are_installed(): void
    {
        $this->assertDirectoryExists(dirname(__DIR__, 3) . '/vendor/ereborcodeforge/durin-core');
        $this->assertDirectoryExists(dirname(__DIR__, 3) . '/vendor/ereborcodeforge/durin-presets');
        $this->assertDirectoryExists(dirname(__DIR__, 3) . '/vendor/ereborcodeforge/durin-architecture');
    }
}
