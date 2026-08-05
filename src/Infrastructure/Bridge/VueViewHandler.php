<?php

declare(strict_types=1);

namespace App\Infrastructure\Bridge;

use Erebor\Mithril\Http\Response;

class VueViewHandler
{
    private array $sharedProps = [];

    public function share(string $key, mixed $value): void
    {
        $this->sharedProps[$key] = $value;
    }

    public function render(string $component, array $props = []): Response
    {
        $props = array_merge($this->sharedProps, $props);
        $isBridge = isset($_SERVER['HTTP_X_DURINS_FORGE_VUE']);

        if ($isBridge) {
            return (new Response())->json([
                'component' => $component,
                'props' => $props,
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
            ]);
        }

        $page = json_encode([
            'component' => $component,
            'props' => $props,
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'version' => $this->getVersion(),
        ]);

        $html = $this->renderRootView($page);
        
        return Response::html($html);
    }

    private function renderRootView(string $pageJson): string
    {
        $viewPath = dirname(__DIR__, 3) . '/resources/views/app.php';
        
        if (!file_exists($viewPath)) {
            return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Durin's Forge</title>
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/resources/js/app.js"></script>
</head>
<body>
    <div id="app" data-page='{$pageJson}'></div>
</body>
</html>
HTML;
        }

        ob_start();
        $page = $pageJson;
        include $viewPath;
        return ob_get_clean();
    }

    private function getVersion(): string
    {
        return '1.0.0';
    }
}
