<?php

declare(strict_types=1);

use EreborCodeForge\Durin\Forge\Console\Application;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use Erebor\Mithril\Environment;

/**
 * Boots the Forge CLI for one invocation.
 *
 * The application is the nearest Composer project of the working directory,
 * or the project that owns the Composer bin proxy. The physical directory of
 * this file is only the executable, including when a path symlink or junction
 * points at a checkout that has its own vendor/.
 *
 * @param list<string> $argv
 */
function durin_boot(string $invokedFile, array $argv): int
{
    $autoload = durin_resolve_autoload($invokedFile);
    require $autoload;

    $root = dirname($autoload, 2);
    ApplicationPath::setRoot($root);

    $envFile = ApplicationPath::path('.env');
    if (is_file($envFile)) {
        Environment::load($envFile);
    }

    return (new Application())->run($argv);
}

function durin_resolve_autoload(string $invokedFile): string
{
    $invoked = realpath($invokedFile);
    if ($invoked === false || !is_file($invoked)) {
        durin_fail('Unable to resolve the Forge executable.');
    }

    $cwd = getcwd();
    $project = ($cwd !== false && $cwd !== '') ? durin_nearest_project($cwd) : null;
    $proxy = durin_proxy_project();
    if ($project !== null && $proxy !== null && !durin_same_path($project['root'], $proxy['root'])) {
        durin_fail(
            "Forge executable context does not match the current project.\n"
            . 'Refusing to run.'
        );
    }

    $selected = $project ?? $proxy;
    if ($selected === null) {
        $where = ($cwd !== false && $cwd !== '') ? $cwd : '(unknown)';
        durin_fail(
            "No Durin project found starting at {$where}.\n"
            . "Change to a Durin application or a durins-forge checkout.\n"
            . 'Create an application with the global durin command: durin new <project>.'
        );
    }

    $root = $selected['root'];
    $name = $selected['meta']['name'] ?? null;
    if ($name === 'ereborcodeforge/durins-forge') {
        $owned = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . basename($invoked);
        if (!durin_same_file($invoked, $owned)) {
            durin_fail('Refusing to run an executable that is not this checkout\'s bin.');
        }

        return durin_project_autoload($root);
    }

    if (!durin_requires_forge($selected['meta'])) {
        $where = ($cwd !== false && $cwd !== '') ? $cwd : $root;
        durin_fail(
            "No Durin project found starting at {$where}.\n"
            . "Change to a Durin application or a durins-forge checkout.\n"
            . 'Create an application with the global durin command: durin new <project>.'
        );
    }

    $owned = $root
        . DIRECTORY_SEPARATOR . 'vendor'
        . DIRECTORY_SEPARATOR . 'ereborcodeforge'
        . DIRECTORY_SEPARATOR . 'durins-forge'
        . DIRECTORY_SEPARATOR . 'bin'
        . DIRECTORY_SEPARATOR . basename($invoked);
    if (!durin_same_file($invoked, $owned)) {
        durin_fail('Refusing to run a Forge executable that does not belong to this application.');
    }

    return durin_project_autoload($root);
}

/**
 * @return array{root: string, meta: array<string, mixed>}|null
 */
function durin_nearest_project(string $start): ?array
{
    $dir = $start;
    while (true) {
        if (durin_contains_vendor_segment($dir)) {
            durin_fail(
                "Refusing to run durins-forge from {$start} because it is inside vendor/.\n"
                . 'Run it from the application directory, outside vendor/.'
            );
        }

        $composerPath = $dir . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_file($composerPath)) {
            $meta = durin_read_composer($composerPath);
            $root = realpath($dir);
            if ($root === false) {
                durin_fail("Unable to resolve the project directory {$dir}.");
            }

            return ['root' => $root, 'meta' => $meta];
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            return null;
        }
        $dir = $parent;
    }
}

/**
 * @return array{root: string, meta: array<string, mixed>}|null
 */
function durin_proxy_project(): ?array
{
    $binDir = $GLOBALS['_composer_bin_dir'] ?? null;
    if (!is_string($binDir) || $binDir === '') {
        return null;
    }

    $autoload = dirname($binDir) . DIRECTORY_SEPARATOR . 'autoload.php';
    $resolved = realpath($autoload);
    if ($resolved === false || !is_file($resolved)) {
        return null;
    }

    $root = dirname($resolved, 2);
    $composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
    if (!is_file($composerPath)) {
        return null;
    }

    return ['root' => $root, 'meta' => durin_read_composer($composerPath)];
}

function durin_project_autoload(string $root): string
{
    $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $resolved = realpath($autoload);
    if ($resolved === false || !is_file($resolved)) {
        durin_fail(
            "Unable to locate Composer autoload.php for this project.\n"
            . "Run `composer install` in the project root.\n"
        );
    }

    return $resolved;
}

/**
 * @param array<string, mixed> $composer
 */
function durin_requires_forge(array $composer): bool
{
    foreach (['require', 'require-dev'] as $section) {
        $requirements = $composer[$section] ?? null;
        if (is_array($requirements) && array_key_exists('ereborcodeforge/durins-forge', $requirements)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<string, mixed>
 */
function durin_read_composer(string $path): array
{
    $raw = file_get_contents($path);
    $decoded = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        durin_fail("composer.json at " . dirname($path) . ' is not valid JSON.');
    }

    return $decoded;
}

function durin_same_file(string $invoked, string $owned): bool
{
    $resolved = realpath($owned);

    return $resolved !== false && is_file($resolved) && durin_same_path($invoked, $resolved);
}

function durin_same_path(string $left, string $right): bool
{
    $normalize = static function (string $path): string {
        $path = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
        }

        return rtrim($path, '/');
    };

    return $normalize($left) === $normalize($right);
}

function durin_contains_vendor_segment(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    foreach (explode('/', $normalized) as $segment) {
        if (strtolower($segment) === 'vendor') {
            return true;
        }
    }

    return false;
}

function durin_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
