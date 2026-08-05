<?php

namespace App\Infrastructure\View;

class Vite
{
    private const BUILD_PATH = '/build';
    private const MANIFEST_PATH = '/build/.vite/manifest.json';

    public static function tags(string $entry): string
    {
        $publicPath = dirname(__DIR__, 3) . '/public';
        
        if (file_exists($publicPath . '/hot')) {
            $url = trim(file_get_contents($publicPath . '/hot'));
            return self::devTags($url, $entry);
        }

        $manifestPath = $publicPath . self::MANIFEST_PATH;
        if (!file_exists($manifestPath)) {
            return "<!-- Vite Manifest not found at {$manifestPath} -->";
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (!isset($manifest[$entry])) {
            return "<!-- Entry {$entry} not found in manifest -->";
        }

        return self::prodTags($manifest, $entry);
    }

    private static function devTags(string $url, string $entry): string
    {
        return <<<HTML
            <script type="module" src="{$url}/@vite/client"></script>
            <script type="module" src="{$url}/{$entry}"></script>
        HTML;
    }

    private static function prodTags(array $manifest, string $entry): string
    {
        $tags = '';
        $chunk = $manifest[$entry];

        if (isset($chunk['css'])) {
            foreach ($chunk['css'] as $css) {
                $tags .= '<link rel="stylesheet" href="' . self::BUILD_PATH . '/' . $css . '">';
            }
        }

        $file = $chunk['file'];
        $tags .= '<script type="module" src="' . self::BUILD_PATH . '/' . $file . '"></script>';
        if (isset($chunk['imports'])) {
            foreach ($chunk['imports'] as $import) {
                if (isset($manifest[$import]['file'])) {
                    $tags .= '<link rel="modulepreload" href="' . self::BUILD_PATH . '/' . $manifest[$import]['file'] . '">';
                }
            }
        }

        return $tags;
    }
}
