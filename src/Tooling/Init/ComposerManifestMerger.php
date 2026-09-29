<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Init;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\RuntimePlan;

/**
 * Merges an existing application composer.json with the ScaffoldPlan template,
 * then refines extra.mithril from the resolved RuntimePlan.
 */
final class ComposerManifestMerger
{
    private const array IDENTITY_KEYS = [
        'name',
        'description',
        'license',
        'authors',
        'homepage',
        'support',
        'keywords',
        'type',
    ];

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $planned
     * @return array<string, mixed>
     */
    public function merge(array $existing, array $planned, RuntimePlan $runtime): array
    {
        $merged = $planned;

        foreach (self::IDENTITY_KEYS as $key) {
            if (array_key_exists($key, $existing)) {
                $merged[$key] = $existing[$key];
            }
        }

        foreach (['autoload', 'autoload-dev'] as $key) {
            if (isset($existing[$key]) && is_array($existing[$key])) {
                $merged[$key] = $existing[$key];
            }
        }

        $merged['require'] = $this->mergeAssoc(
            is_array($existing['require'] ?? null) ? $existing['require'] : [],
            is_array($planned['require'] ?? null) ? $planned['require'] : [],
        );
        $merged['require-dev'] = $this->mergeAssoc(
            is_array($existing['require-dev'] ?? null) ? $existing['require-dev'] : [],
            is_array($planned['require-dev'] ?? null) ? $planned['require-dev'] : [],
        );

        $merged['scripts'] = $this->mergeAssoc(
            is_array($existing['scripts'] ?? null) ? $existing['scripts'] : [],
            is_array($planned['scripts'] ?? null) ? $planned['scripts'] : [],
        );

        if (isset($planned['bin'])) {
            $merged['bin'] = $planned['bin'];
        } elseif (isset($existing['bin'])) {
            $merged['bin'] = $existing['bin'];
        }

        $extra = is_array($existing['extra'] ?? null) ? $existing['extra'] : [];
        $plannedExtra = is_array($planned['extra'] ?? null) ? $planned['extra'] : [];
        $extra['mithril'] = $this->mergeAssoc(
            is_array($extra['mithril'] ?? null) ? $extra['mithril'] : [],
            is_array($plannedExtra['mithril'] ?? null) ? $plannedExtra['mithril'] : [],
        );
        foreach ($plannedExtra as $key => $value) {
            if ($key === 'mithril') {
                continue;
            }
            $extra[$key] = $value;
        }
        $merged['extra'] = $extra;

        if (isset($existing['config']) && is_array($existing['config'])) {
            $merged['config'] = $this->mergeAssoc(
                $existing['config'],
                is_array($planned['config'] ?? null) ? $planned['config'] : [],
            );
        }

        return $this->refineForRuntime($merged, $runtime);
    }

    /**
     * @param array<string, mixed> $composer
     * @return array<string, mixed>
     */
    public function refineForRuntime(array $composer, RuntimePlan $runtime): array
    {
        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $mithril = is_array($extra['mithril'] ?? null) ? $extra['mithril'] : [];

        if ($runtime->isJobExecution()) {
            if (!isset($mithril['job_kernel']) || !is_string($mithril['job_kernel']) || $mithril['job_kernel'] === '') {
                $mithril['job_kernel'] = 'App\\JobKernel';
            }
            unset($mithril['kernel']);
        } else {
            if (!isset($mithril['kernel']) || !is_string($mithril['kernel']) || $mithril['kernel'] === '') {
                $mithril['kernel'] = 'App\\Kernel';
            }
            unset($mithril['job_kernel']);
        }

        if ($runtime->usesEregion()) {
            if (!isset($mithril['eregion']) || !is_string($mithril['eregion']) || $mithril['eregion'] === '') {
                $mithril['eregion'] = 'v0.3.0';
            }
            if (!isset($mithril['eregion_repo']) || !is_string($mithril['eregion_repo']) || $mithril['eregion_repo'] === '') {
                $mithril['eregion_repo'] = 'EreborCodeForge/eregion';
            }
        } else {
            unset($mithril['eregion'], $mithril['eregion_repo']);
        }

        $extra['mithril'] = $mithril;
        $composer['extra'] = $extra;

        return $composer;
    }

    public function encode(array $composer): string
    {
        $json = json_encode(
            $composer,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return $json . "\n";
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function mergeAssoc(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            $base[$key] = $value;
        }

        return $base;
    }
}
