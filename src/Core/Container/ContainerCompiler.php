<?php

declare(strict_types=1);

namespace App\Core\Container;

/**
 * Converte o descriptor (DescriptorProvider) em PHP executável para loadCompiled().
 * Gera closures que o Container do Mithril espera em factories/singletons.
 */
final class ContainerCompiler
{
    /**
     * Gera o conteúdo PHP do arquivo de cache (return ['factories' => ..., 'singletons' => ..., 'preloaded' => ...]).
     *
     * @param array{singletons: array, factories: array, bind: array, preloaded: array} $descriptor
     */
    public function compile(array $descriptor): string
    {
        $singletons = $this->compileEntries(
            array_merge($this->baseSingletons(), $descriptor['singletons'] ?? [])
        );
        $factories = $this->compileEntries($descriptor['factories'] ?? []);
        $bindAsFactories = $this->compileEntries($descriptor['bind'] ?? []);
        $factories = array_merge($factories, $bindAsFactories);
        $preloaded = $descriptor['preloaded'] ?? [];

        $singletonsPhp = $this->renderMap($singletons);
        $factoriesPhp = $this->renderMap($factories);
        $preloadedPhp = '[]';

        return <<<PHP
<?php

declare(strict_types=1);

/**
 * Cache para Erebor\Mithril\Container::loadCompiled()
 * Gerado por: php bin/durins-forge container:compile (DescriptorProvider + ContainerCompiler)
 * Não edite manualmente. Para limpar: container:clear
 */

return [
    'factories' => [
{$factoriesPhp}
    ],
    'singletons' => [
{$singletonsPhp}
    ],
    'preloaded' => {$preloadedPhp},
];

PHP;
    }

    /** @return array<string, array{new?: string, deps: array, builder?: string}> */
    private function baseSingletons(): array
    {
        return [
            \Erebor\Mithril\Container::class => ['new' => null, 'deps' => [], 'self' => true],
            \Erebor\Mithril\Router::class => ['new' => \Erebor\Mithril\Router::class, 'deps' => []],
            \Erebor\Mithril\Routing\Contracts\HandlerResolver::class => [
                'new' => \App\Core\Routing\ControllerHandlerResolver::class,
                'deps' => [\Erebor\Mithril\Container::class],
            ],
            \App\Core\Exceptions\Handler::class => ['new' => \App\Core\Exceptions\Handler::class, 'deps' => []],
        ];
    }

    /**
     * Converte cada entrada do descriptor em código PHP de closure.
     *
     * @param array<string, array{new?: string, deps?: array, builder?: string, self?: bool}> $entries
     * @return array<string, string> abstract => PHP closure source
     */
    private function compileEntries(array $entries): array
    {
        $out = [];
        foreach ($entries as $abstract => $def) {
            $def = is_array($def) ? $def : ['new' => $def, 'deps' => []];
            $deps = $def['deps'] ?? [];
            if (!empty($def['self'])) {
                $body = 'return $c;';
            } elseif (isset($def['call']) && $def['call'] !== '') {
                $body = 'return \\' . $def['call'] . '();';
            } elseif (isset($def['builder']) && $def['builder'] !== '') {
                $builder = $def['builder'];
                $body = 'return \\' . $builder . '($c);';
            } elseif (isset($def['new']) && $def['new'] !== null) {
                $class = $def['new'];
                $argsPhp = $this->compileConstructorArgs($deps, $def['args'] ?? []);
                $body = 'return new \\' . $class . '(' . implode(', ', $argsPhp) . ');';
            } else {
                continue;
            }
            $out[$abstract] = 'function ($c) { ' . $body . ' }';
        }
        return $out;
    }

    /**
     * Constrói a lista de argumentos PHP para o construtor: literais (var_export) ou $c->get(Class::class).
     * Deps que parecem FQCN (ex: App\Foo) viram container; o resto vira literal.
     *
     * @param array<int, mixed> $deps
     * @param array<int, mixed> $args literais avaliados em tempo de compilação
     * @return list<string> trechos PHP
     */
    private function compileConstructorArgs(array $deps, array $args = []): array
    {
        $out = [];
        foreach ($args as $literal) {
            $out[] = var_export($literal, true);
        }
        foreach ($deps as $dep) {
            if (is_string($dep) && preg_match('/^[a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*$/', $dep)) {
                $out[] = '$c->get(\\' . $dep . '::class)';
            } else {
                $out[] = var_export($dep, true);
            }
        }
        return $out;
    }

    /**
     * @param array<string, string> $map abstract => closure PHP source
     */
    private function renderMap(array $map): string
    {
        $lines = [];
        foreach ($map as $abstract => $closurePhp) {
            $key = var_export($abstract, true);
            $lines[] = '        ' . $key . ' => ' . $closurePhp . ',';
        }
        return implode("\n", $lines);
    }
}
