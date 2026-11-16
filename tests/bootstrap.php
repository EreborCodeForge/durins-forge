<?php

declare(strict_types=1);

use EreborCodeForge\Durin\Forge\Support\ApplicationPath;

require dirname(__DIR__) . '/vendor/autoload.php';

$mithrilCandidates = [
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'mithrilphp' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
    dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'mithrilphp' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR,
];
$mithrilSrc = null;
foreach ($mithrilCandidates as $candidate) {
    if (is_dir($candidate)) {
        $mithrilSrc = $candidate;
        break;
    }
}
if (is_string($mithrilSrc)) {
    spl_autoload_register(static function (string $class) use ($mithrilSrc): void {
        $prefix = 'Erebor\\Mithril\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        $path = $mithrilSrc . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($path)) {
            require_once $path;
        }
    }, true, true);
}

ApplicationPath::setRoot(dirname(__DIR__));
