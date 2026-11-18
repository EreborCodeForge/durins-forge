<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Console;

use EreborCodeForge\Durin\Presets\Capability\WorkloadOptionException;

/**
 * Parses init/new flags and rejects unknown options and conflicting repeats
 * before any filesystem change. Mithril's ArgParser keeps the last value.
 */
final class InitOptionReader
{
    /** @var list<string> */
    public const array KNOWN = [
        'preset',
        'with',
        'supervisor',
        'scaling',
        'workers-min',
        'workers-max',
        'resource-class',
        'memory-mb',
        'progress',
        'skip-runtime-install',
        'no-interaction',
    ];

    /**
     * @param list<string> $args
     * @return array<string, string|bool>
     */
    public function read(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if (!is_string($arg) || !str_starts_with($arg, '--')) {
                continue;
            }

            $body = substr($arg, 2);
            $eq = strpos($body, '=');
            $key = $eq === false ? $body : substr($body, 0, $eq);
            $value = $eq === false ? true : substr($body, $eq + 1);

            if (!in_array($key, self::KNOWN, true)) {
                throw new WorkloadOptionException("Unknown option --{$key}.");
            }

            if (array_key_exists($key, $options) && $options[$key] !== $value) {
                throw new WorkloadOptionException("Conflicting values for --{$key}.");
            }

            $options[$key] = $value;
        }

        return $options;
    }

    /**
     * @param array<string, string|bool> $options
     */
    public function optionalInt(array $options, string $key): ?int
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        $value = $options[$key];
        if (!is_string($value) || preg_match('/^-?\d+$/', $value) !== 1) {
            throw new WorkloadOptionException("Invalid --{$key}: expected an integer.");
        }

        return (int) $value;
    }

    /**
     * @param array<string, string|bool> $options
     */
    public function optionalString(array $options, string $key): ?string
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        $value = $options[$key];
        if (!is_string($value)) {
            throw new WorkloadOptionException("Invalid --{$key}: expected a value.");
        }

        return $value;
    }
}
