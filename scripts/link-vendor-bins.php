<?php

declare(strict_types=1);

/**
 * Composer does not install the root package's "bin" into vendor/bin.
 * This script mirrors bin/durin (+ durins-forge) there for the canonical DX path.
 */
$root = dirname(__DIR__);
$vendorBin = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';

if (!is_dir($vendorBin)) {
    fwrite(STDERR, "vendor/bin missing — run composer install first.\n");
    exit(1);
}

$bins = ['durin', 'durins-forge'];

foreach ($bins as $name) {
    $source = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $name;
    if (!is_file($source)) {
        fwrite(STDERR, "Missing {$source}\n");
        exit(1);
    }

    $target = $vendorBin . DIRECTORY_SEPARATOR . $name;
    $proxy = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/%NAME%';

PHP;
    $proxy = str_replace('%NAME%', $name, $proxy);
    // Unix shebang must be LF (WSL/Git Bash break on php\r)
    $proxy = str_replace("\r\n", "\n", $proxy);
    file_put_contents($target, $proxy);

    $bat = $vendorBin . DIRECTORY_SEPARATOR . $name . '.bat';
    $batBody = "@ECHO OFF\r\n"
        . "setlocal DISABLEDELAYEDEXPANSION\r\n"
        . "SET BIN_TARGET=%~dp0/../../bin/{$name}\r\n"
        . "php \"%BIN_TARGET%\" %*\r\n";
    file_put_contents($bat, $batBody);

    echo "Linked vendor/bin/{$name}\n";
}
