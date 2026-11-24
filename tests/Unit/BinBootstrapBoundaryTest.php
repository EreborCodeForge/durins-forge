<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BinBootstrapBoundaryTest extends TestCase
{
    public function test_checkout_without_autoload_does_not_load_a_parent_project(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-bin-' . bin2hex(random_bytes(4));
        $parentAutoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $checkout = $root . DIRECTORY_SEPARATOR . 'nested-forge';
        $binDir = $checkout . DIRECTORY_SEPARATOR . 'bin';
        mkdir($binDir, 0777, true);
        mkdir(dirname($parentAutoload), 0777, true);

        file_put_contents($parentAutoload, "<?php\nfwrite(STDERR, \"PARENT_AUTOLOAD\\n\");\n");
        file_put_contents(
            $checkout . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode(['name' => 'ereborcodeforge/durins-forge'], JSON_THROW_ON_ERROR),
        );

        $binSource = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin';
        copy($binSource . DIRECTORY_SEPARATOR . 'durins-forge', $binDir . DIRECTORY_SEPARATOR . 'durins-forge');
        copy($binSource . DIRECTORY_SEPARATOR . 'bootstrap.php', $binDir . DIRECTORY_SEPARATOR . 'bootstrap.php');

        $stderr = $root . DIRECTORY_SEPARATOR . 'stderr.txt';
        $script = $binDir . DIRECTORY_SEPARATOR . 'durins-forge';
        $command = [PHP_BINARY, $script];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderr, 'wb'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $checkout, null, ['bypass_shell' => true]);
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        $error = (string) file_get_contents($stderr);
        $this->removeTree($root);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('composer install', $error);
        self::assertStringNotContainsString('PARENT_AUTOLOAD', $error);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}
