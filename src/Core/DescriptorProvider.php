<?php

declare(strict_types=1);

namespace App\Core;

final class DescriptorProvider
{
    private const KEYS = ['singletons', 'factories', 'bind', 'preloaded'];

    /**
     * Monta o descriptor agregado a partir dos providers informados.
     *
     * @param list<class-string<ServiceProvider>|ServiceProvider> $providers Classes ou instâncias de ServiceProvider
     * @return array{singletons: array<string, array{new: string, deps: array}>, factories: array, bind: array, preloaded: array}
     */
    public static function build(array $providers): array
    {
        $merged = self::emptyStructure();

        foreach ($providers as $provider) {
            $instance = is_object($provider) ? $provider : new $provider();
            if (!($instance instanceof ServiceProvider)) {
                continue;
            }
            if (!method_exists($instance, 'describe')) {
                continue;
            }
            $described = $instance->describe();
            self::merge($merged, $described);
        }

        return $merged;
    }

    /**
     * Monta o descriptor agregado a partir do discovery (tag 'provider').
     * Equivalente a build() com a lista de classes retornada por DiscoveryServiceProvider::discover().
     *
     * @return array{singletons: array<string, array{new: string, deps: array}>, factories: array, bind: array, preloaded: array}
     */
    public static function buildFromDiscovery(string $tag = 'provider'): array
    {
        $discovered = DiscoveryServiceProvider::discover(tag: $tag);
        $classes = array_map(fn(array $m) => $m['class'], $discovered);
        $filtered = array_filter($classes, fn(string $c) => is_subclass_of($c, ServiceProvider::class));
        return self::build(array_values($filtered));
    }

    /**
     * Estrutura vazia no formato esperado (singletons, factories, bind, preloaded).
     *
     * @return array{singletons: array, factories: array, bind: array, preloaded: array}
     */
    public static function emptyStructure(): array
    {
        return [
            'singletons' => [],
            'factories'  => [],
            'bind'       => [],
            'preloaded'  => [],
        ];
    }

    private static function merge(array &$merged, array $described): void
    {
        foreach (self::KEYS as $key) {
            if (!isset($described[$key]) || !is_array($described[$key])) {
                continue;
            }
            $merged[$key] = array_merge($merged[$key], $described[$key]);
        }
    }
}
