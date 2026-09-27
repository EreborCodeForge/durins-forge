<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Package;

use EreborCodeForge\Durin\Presets\Registry\DefaultPresetRegistryFactory;
use EreborCodeForge\Durin\Core\Contract\ProjectOptions;
use PHPUnit\Framework\TestCase;

final class GeneratedPresetContractTest extends TestCase
{
    public function test_generated_http_presets_require_durins_forge_and_vendor_bin_docs(): void
    {
        $engine = (new DefaultPresetRegistryFactory())->engine();

        foreach (['minimal', 'service'] as $preset) {
            $plan = $engine->plan(new ProjectOptions('demo', $preset, sys_get_temp_dir()));
            $files = [];
            foreach ($plan->actions() as $action) {
                $files[$action->relativePath] = $action->contents ?? '';
            }

            $this->assertArrayHasKey('composer.json', $files);
            $composer = json_decode($files['composer.json'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('^0.1', $composer['require']['ereborcodeforge/durins-forge']);
            $this->assertArrayNotHasKey('ereborcodeforge/mithrilphp', $composer['require']);
            if ($this->presetsPackageAtLeast('0.1.2')) {
                $this->assertNoNestedDurinVcsRepositories($composer, 'live engine:' . $preset);
            }

            $this->assertArrayHasKey('README.md', $files);
            $this->assertStringContainsString('vendor/bin/durin doctor', $files['README.md']);
            $this->assertStringContainsString('vendor/bin/durin dev', $files['README.md']);
            $this->assertStringNotContainsString('php bin/durin', $files['README.md']);

            $this->assertArrayHasKey('src/Kernel.php', $files);
            $this->assertStringContainsString('namespace App;', $files['src/Kernel.php']);
            $this->assertStringContainsString('HttpApplicationKernel', $files['src/Kernel.php']);
        }
    }

    public function test_fixture_composer_files_match_forge_dependency_contract(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['minimal', 'service', 'worker'] as $preset) {
            $path = $root . "/tests/Fixtures/generated-{$preset}/composer.json";
            $composer = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(
                '^0.1',
                $composer['require']['ereborcodeforge/durins-forge'],
                $preset
            );
            $this->assertNoNestedDurinVcsRepositories($composer, 'fixture:' . $preset);
        }
    }

    private function presetsPackageAtLeast(string $minimum): bool
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return false;
        }

        $version = \Composer\InstalledVersions::getPrettyVersion('ereborcodeforge/durin-presets');
        if ($version === null) {
            return false;
        }

        // Strip leading "v" if present.
        $normalized = ltrim($version, 'v');

        return version_compare($normalized, $minimum, '>=');
    }

    /**
     * Nested Durin package VCS entries are unnecessary once those packages are on Packagist.
     * A single transitional Forge VCS entry is allowed until Forge itself is published.
     *
     * @param array<string, mixed> $composer
     */
    private function assertNoNestedDurinVcsRepositories(array $composer, string $context): void
    {
        $repos = $composer['repositories'] ?? [];
        $urls = [];
        foreach ($repos as $repo) {
            if (!is_array($repo)) {
                continue;
            }
            $urls[] = (string) ($repo['url'] ?? '');
        }

        $forbidden = [
            'https://github.com/EreborCodeForge/durin-core',
            'https://github.com/EreborCodeForge/durin-presets',
            'https://github.com/EreborCodeForge/durin-architecture',
        ];

        foreach ($forbidden as $url) {
            $this->assertNotContains(
                $url,
                $urls,
                "{$context}: generated composer must not list nested Durin VCS {$url}"
            );
        }

        // Until Forge is on Packagist, exactly one Forge VCS entry (or none) is acceptable.
        $forgeUrl = 'https://github.com/EreborCodeForge/durins-forge';
        $other = array_values(array_filter(
            $urls,
            static fn (string $u): bool => $u !== '' && $u !== $forgeUrl
        ));
        $this->assertSame([], $other, "{$context}: unexpected Composer repositories: " . implode(', ', $other));
    }
}
