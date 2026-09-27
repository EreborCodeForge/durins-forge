<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Support;

use EreborCodeForge\Durin\Core\Manifest\DurinManifestException;
use EreborCodeForge\Durin\Core\Project\ProjectDiscovery;
use RuntimeException;

/**
 * Explicit application root — never derived from the Forge package install path.
 */
final class ApplicationPath
{
    private static ?string $root = null;

    private static string $applicationNamespace = 'App\\';

    public static function setRoot(string $root): void
    {
        $resolved = realpath($root) ?: $root;
        if (!is_dir($resolved)) {
            throw new RuntimeException("Application root is not a directory: {$root}");
        }

        self::$root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved), DIRECTORY_SEPARATOR);
    }

    public static function root(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        self::discoverFromCwd();

        if (self::$root === null) {
            throw new RuntimeException(
                'Application root is not set. Call ApplicationPath::setRoot() or run from a Durin project directory.'
            );
        }

        return self::$root;
    }

    public static function path(string $path = ''): string
    {
        $base = self::root();
        if ($path === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    public static function setApplicationNamespace(string $namespace): void
    {
        self::$applicationNamespace = rtrim($namespace, '\\') . '\\';
    }

    public static function applicationNamespace(): string
    {
        return self::$applicationNamespace;
    }

    public static function reset(): void
    {
        self::$root = null;
        self::$applicationNamespace = 'App\\';
    }

    public static function isSet(): bool
    {
        return self::$root !== null;
    }

    /**
     * Resolve root from CWD via durin-core project discovery.
     * Rejects the Forge package install directory under vendor/.
     */
    public static function discoverFromCwd(?string $startDirectory = null): void
    {
        $start = $startDirectory;
        if ($start === null) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Unable to determine working directory for application root.');
            }
            $start = $cwd;
        }

        try {
            $root = (new ProjectDiscovery())->locateRoot($start);
        } catch (DurinManifestException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        if (self::isForgeVendorInstallPath($root)) {
            throw new RuntimeException(
                'Refusing to use the Durin Forge package install path as application root. '
                . 'Run the CLI from the consuming project directory or call ApplicationPath::setRoot().'
            );
        }

        self::setRoot($root);
    }

    public static function forgePackageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function isForgeVendorInstallPath(string $root): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $root));

        return str_contains($normalized, '/vendor/ereborcodeforge/durins-forge')
            || str_contains($normalized, '/vendor/ereborcodeforge/durin-forge');
    }
}
