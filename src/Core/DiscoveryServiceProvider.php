<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core;

use EreborCodeForge\Durin\Forge\Core\Attributes\Discoverable;
use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use Erebor\Mithril\Container;
use Erebor\Mithril\Environment;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class DiscoveryServiceProvider implements ServiceProvider
{
    public function register(Container $c): void
    {
        $providers = self::discover(tag: 'provider');

        foreach ($providers as $meta) {
            $providerClass = $meta['class'];

            if ($providerClass === self::class) {
                continue;
            }

            if (!is_subclass_of($providerClass, ServiceProvider::class)) {
                continue;
            }

            /** @var ServiceProvider $provider */
            $provider = new $providerClass();
            $provider->register($c);
        }
    }

    public function describe(): array
    {
        return DescriptorProvider::emptyStructure();
    }

    /**
     * Discover #[Discoverable] classes in Forge package src and application src.
     *
     * @return array<int, array{class: class-string, tag: string}>
     */
    public static function discover(?string $tag = null): array
    {
        $useCache = Environment::get('APP_DEBUG', 'false') !== 'true' && $tag !== null;
        if ($useCache) {
            $cachePath = self::discoveryCachePath($tag);
            if (file_exists($cachePath)) {
                return require $cachePath;
            }
        }

        $found = self::runDiscovery($tag);

        if ($useCache) {
            self::writeDiscoveryCache($tag, $found);
        }

        return $found;
    }

    private static function runDiscovery(?string $tag): array
    {
        $found = [];
        $seen = [];

        foreach (self::scanRoots() as $root) {
            $base = $root['path'];
            $namespace = $root['namespace'];
            if (!is_dir($base)) {
                continue;
            }

            $realBase = realpath($base);
            if ($realBase === false) {
                continue;
            }
            $realBase .= DIRECTORY_SEPARATOR;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fqcn = self::fqcnFromFile($file->getPathname(), $realBase, $namespace);
                if (!$fqcn || isset($seen[$fqcn]) || !class_exists($fqcn)) {
                    continue;
                }
                $seen[$fqcn] = true;

                try {
                    $ref = new ReflectionClass($fqcn);
                    if ($ref->isAbstract() || $ref->isInterface()) {
                        continue;
                    }

                    foreach ($ref->getAttributes(Discoverable::class) as $attr) {
                        /** @var Discoverable $meta */
                        $meta = $attr->newInstance();
                        if ($tag !== null && $meta->tag !== $tag && !str_starts_with((string) $meta->tag, $tag . '.')) {
                            continue;
                        }
                        $found[] = [
                            'class' => $fqcn,
                            'tag' => $meta->tag,
                        ];
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<array{path: string, namespace: string}>
     */
    private static function scanRoots(): array
    {
        $forgeSrc = ApplicationPath::forgePackageRoot() . DIRECTORY_SEPARATOR . 'src';
        $roots = [
            [
                'path' => $forgeSrc,
                'namespace' => 'EreborCodeForge\\Durin\\Forge\\',
            ],
        ];

        $appSrc = base_path('src');
        $forgeReal = realpath($forgeSrc);
        $appReal = realpath($appSrc);
        if ($appReal !== false && $appReal !== $forgeReal) {
            $roots[] = [
                'path' => $appSrc,
                'namespace' => ApplicationPath::applicationNamespace(),
            ];
        }

        return $roots;
    }

    private static function discoveryCachePath(string $tag): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $tag);

        return base_path('storage/framework/cache/discovered_providers_' . $safe . '.php');
    }

    private static function writeDiscoveryCache(string $tag, array $found): void
    {
        $path = self::discoveryCachePath($tag);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $export = '<?php return ' . var_export($found, true) . ';';
        file_put_contents($path, $export);
    }

    private static function fqcnFromFile(string $path, string $realBaseWithSep, string $namespace): ?string
    {
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $realBaseWithSep)) {
            return null;
        }

        $relative = substr($real, strlen($realBaseWithSep));
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return $namespace . preg_replace('/\.php$/', '', $relative);
    }
}
