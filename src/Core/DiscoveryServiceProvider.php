<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Attributes\Discoverable;
use App\Core\ServiceProvider;
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
        // Descobre apenas classes marcadas como providers
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
     * Descobre classes em src/ marcadas com #[Discoverable].
     * Em produção (APP_DEBUG !== true) usa cache em arquivo para não rodar scan+reflection a cada request.
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
        $base = base_path('src');
        if (!is_dir($base)) {
            return [];
        }

        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $fqcn = self::fqcnFromFile($file->getPathname());
            if (!$fqcn || !class_exists($fqcn)) {
                continue;
            }

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
                        'tag'   => $meta->tag,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $found;
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

    private static function fqcnFromFile(string $path): ?string
    {
        $base = realpath(base_path('src')) . DIRECTORY_SEPARATOR;

        if (!str_starts_with($path, $base)) {
            return null;
        }

        $relative = substr($path, strlen($base));
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return 'App\\' . preg_replace('/\.php$/', '', $relative);
    }
}
