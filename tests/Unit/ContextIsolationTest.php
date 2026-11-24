<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit;

use EreborCodeForge\Durin\Forge\Support\ApplicationPath;
use EreborCodeForge\Durin\Forge\Tooling\Init\ApplicationInitializer;
use EreborCodeForge\Durin\Forge\Tooling\Progress\InitProgressReporter;
use PHPUnit\Framework\TestCase;

final class ContextIsolationTest extends TestCase
{
    private string $tempRoot;

    private string $realPackage;

    /** @var array<string, string> */
    private array $realHashes = [];

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'durin-context-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot);
        $this->realPackage = dirname(__DIR__, 2);
        $this->realHashes = $this->hashes($this->realPackage, [
            'bin/bootstrap.php',
            'bin/durin',
            'bin/durins-forge',
            'composer.json',
            'src/Tooling/Init/ApplicationInitializer.php',
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tempRoot);
    }

    public function testDistInstallUsesTheConsumerAndDoesNotTouchTheCheckout(): void
    {
        $this->assertConsumerContext('mirror', 'dist');
    }

    public function testMirroredPathInstallUsesTheConsumerAndDoesNotTouchTheCheckout(): void
    {
        $this->assertConsumerContext('mirror', 'mirror');
    }

    public function testSymlinkedCheckoutWithVendorUsesTheConsumer(): void
    {
        $this->assertConsumerContext('symlink', 'symlink');
    }

    public function testWindowsJunctionToCheckoutWithVendorUsesTheConsumer(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('Junctions are a Windows filesystem feature.');
        }

        $this->assertConsumerContext('junction', 'junction');
    }

    public function testCheckoutExecutionStaysInTheCheckout(): void
    {
        $layout = $this->layout('symlink');
        [$code, $stdout, $stderr] = $this->launch(
            [PHP_BINARY, $layout['checkoutBin'], '--version'],
            $layout['checkout'],
        );

        self::assertSame(0, $code, $stderr);
        self::assertSame('checkout', $this->marker($layout));
        self::assertStringContainsString('durins-forge ', $stdout);
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
        $this->assertRealPackageUnchanged();
    }

    public function testSubdirectoryKeepsTheConsumer(): void
    {
        $layout = $this->layout('symlink');
        $nested = $layout['app'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Feature';
        mkdir($nested);
        [$code, , $stderr] = $this->launch(
            [PHP_BINARY, $layout['packageBin'], '--version'],
            $nested,
        );

        self::assertSame(0, $code, $stderr);
        self::assertSame('consumer', $this->marker($layout));
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
    }

    public function testLegacyDurinAliasAndLocalBinUseTheConsumer(): void
    {
        $layout = $this->layout('symlink');
        foreach (['durin', 'durins-forge'] as $name) {
            file_put_contents($layout['marker'], '');
            $proxy = $layout['app'] . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $name;
            [$code, , $stderr] = $this->launch([PHP_BINARY, $proxy, '--version'], $layout['app']);
            self::assertSame(0, $code, $name . PHP_EOL . $stderr);
            self::assertSame('consumer', $this->marker($layout), $name);
        }
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
    }

    public function testGlobalLauncherUsesTheConsumer(): void
    {
        $launcher = dirname($this->realPackage) . DIRECTORY_SEPARATOR . 'durin-installer' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge';
        if (!is_file($launcher) || !is_file(dirname($this->realPackage) . '/durin-installer/vendor/autoload.php')) {
            self::markTestSkipped('The installer checkout is not installed next to durins-forge.');
        }

        $layout = $this->layout('symlink');
        [$code, $stdout, $stderr] = $this->launch([PHP_BINARY, $launcher, '--version'], $layout['app']);

        self::assertSame(0, $code, $stderr);
        self::assertSame('consumer', $this->marker($layout));
        self::assertStringContainsString('durins-forge ', $stdout);
        self::assertStringNotContainsString('Durin Installer', $stdout);
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
        $this->assertRealPackageUnchanged();
    }

    public function testNestedProjectDoesNotUseTheOuterApplication(): void
    {
        $layout = $this->layout('symlink');
        $inner = $layout['app'] . DIRECTORY_SEPARATOR . 'nested';
        mkdir($inner);
        file_put_contents($inner . DIRECTORY_SEPARATOR . 'composer.json', '{"name":"other/nested"}');

        [$code, , $stderr] = $this->launch([PHP_BINARY, $layout['packageBin'], '--version'], $inner);

        self::assertSame(1, $code);
        self::assertStringContainsString('No Durin project found', $stderr);
        self::assertFileDoesNotExist($layout['marker']);
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
    }

    public function testInitOfALinkedConsumerDoesNotMutateTheCheckout(): void
    {
        $layout = $this->layout('symlink');
        [$code, $stdout, $stderr] = $this->launch(
            [PHP_BINARY, $layout['packageBin'], 'init', '--preset=minimal', '--skip-runtime-install'],
            $layout['app'],
        );

        self::assertSame(0, $code, $stdout . $stderr);
        self::assertSame('consumer', $this->marker($layout));
        $yaml = (string) file_get_contents($layout['app'] . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertStringContainsString('preset: minimal', $yaml);
        self::assertFileDoesNotExist($layout['checkout'] . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertFileDoesNotExist($layout['checkout'] . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Kernel.php');
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
        $this->assertRealPackageUnchanged();
    }

    public function testInitRefusesToTurnTheFrameworkPackageIntoAnApplication(): void
    {
        $layout = $this->layout('symlink');
        [$code, $stdout, $stderr] = $this->launch(
            [PHP_BINARY, $layout['checkoutBin'], 'init', '--preset=minimal', '--skip-runtime-install'],
            $layout['checkout'],
        );

        self::assertSame(1, $code, $stdout . $stderr);
        self::assertStringContainsString(
            'Refusing to initialize the ereborcodeforge/durins-forge package',
            $stdout . $stderr,
        );
        self::assertSame('checkout', $this->marker($layout));
        self::assertFileDoesNotExist($layout['checkout'] . DIRECTORY_SEPARATOR . 'durin.yaml');
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']));
        $this->assertRealPackageUnchanged();
    }

    public function testInitializerRejectsTheFrameworkPackageBeforeWriting(): void
    {
        $root = $this->tempRoot . DIRECTORY_SEPARATOR . 'named-like-forge';
        mkdir($root);
        file_put_contents($root . DIRECTORY_SEPARATOR . 'composer.json', '{"name":"ereborcodeforge/durins-forge"}');
        $before = $this->hashes($root);

        try {
            (new ApplicationInitializer())->initialize($root, 'minimal', new InitProgressReporter(), true);
            self::fail('Init was expected to reject the framework package.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Refusing to initialize', $e->getMessage());
        }

        self::assertSame($before, $this->hashes($root));
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . 'durin.yaml');
        ApplicationPath::reset();
    }

    private function assertConsumerContext(string $mode, string $label): void
    {
        $layout = $this->layout($mode);
        [$code, $stdout, $stderr] = $this->launch(
            [PHP_BINARY, $layout['packageBin'], '--version'],
            $layout['app'],
        );

        self::assertSame(0, $code, $label . PHP_EOL . $stderr);
        self::assertSame('consumer', $this->marker($layout), $label);
        self::assertStringContainsString('durins-forge ', $stdout);
        self::assertSame($layout['checkoutHashes'], $this->hashes($layout['checkout']), $label);
        $this->assertRealPackageUnchanged();
    }

    /**
     * @return array{
     *     app: string,
     *     checkout: string,
     *     packageBin: string,
     *     checkoutBin: string,
     *     marker: string,
     *     checkoutHashes: array<string, string>
     * }
     */
    private function layout(string $mode): array
    {
        $checkout = $this->tempRoot . DIRECTORY_SEPARATOR . 'checkout-' . $mode;
        $app = $this->tempRoot . DIRECTORY_SEPARATOR . 'app-' . $mode;
        $marker = $this->tempRoot . DIRECTORY_SEPARATOR . 'marker-' . $mode . '.txt';
        $this->writeCheckout($checkout, $marker);
        $this->writeConsumer($app, $checkout, $marker, $mode);
        $packageBin = $app
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'ereborcodeforge'
            . DIRECTORY_SEPARATOR . 'durins-forge'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'durins-forge';

        return [
            'app' => $app,
            'checkout' => $checkout,
            'packageBin' => $packageBin,
            'checkoutBin' => $checkout . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'durins-forge',
            'marker' => $marker,
            'checkoutHashes' => $this->hashes($checkout),
        ];
    }

    private function writeCheckout(string $checkout, string $marker): void
    {
        $bin = $checkout . DIRECTORY_SEPARATOR . 'bin';
        mkdir($bin, 0777, true);
        mkdir($checkout . DIRECTORY_SEPARATOR . 'vendor', 0777, true);
        $source = $this->realPackage . DIRECTORY_SEPARATOR . 'bin';
        foreach (['durin', 'durins-forge', 'bootstrap.php'] as $file) {
            copy($source . DIRECTORY_SEPARATOR . $file, $bin . DIRECTORY_SEPARATOR . $file);
        }
        file_put_contents(
            $checkout . DIRECTORY_SEPARATOR . 'composer.json',
            json_encode(['name' => 'ereborcodeforge/durins-forge'], JSON_THROW_ON_ERROR),
        );
        file_put_contents($checkout . DIRECTORY_SEPARATOR . 'sentinel.txt', 'framework-checkout');
        $realAutoload = $this->realPackage . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        file_put_contents(
            $checkout . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
            "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'checkout');\nrequire " . var_export($realAutoload, true) . ";\n",
        );
    }

    private function writeConsumer(string $app, string $checkout, string $marker, string $mode): void
    {
        $package = $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'ereborcodeforge' . DIRECTORY_SEPARATOR . 'durins-forge';
        $bin = $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        mkdir(dirname($package), 0777, true);
        mkdir($bin, 0777, true);
        mkdir($app . DIRECTORY_SEPARATOR . 'src', 0777, true);
        $this->placePackage($checkout, $package, $mode);
        file_put_contents($app . DIRECTORY_SEPARATOR . 'composer.json', json_encode([
            'name' => 'app/orders',
            'require' => [
                'php' => '^8.5',
                'ereborcodeforge/durins-forge' => '^0.4',
            ],
            'autoload' => ['psr-4' => ['App\\' => 'src/']],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($app . DIRECTORY_SEPARATOR . '.env.example', "APP_NAME=orders\n");
        $realAutoload = $this->realPackage . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        file_put_contents(
            $app . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
            "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'consumer');\nrequire " . var_export($realAutoload, true) . ";\n",
        );
        foreach (['durin', 'durins-forge'] as $name) {
            file_put_contents(
                $bin . DIRECTORY_SEPARATOR . $name,
                "<?php\n\$GLOBALS['_composer_bin_dir'] = __DIR__;\nrequire __DIR__ . '/../ereborcodeforge/durins-forge/bin/{$name}';\n",
            );
        }
    }

    private function placePackage(string $checkout, string $package, string $mode): void
    {
        if ($mode === 'mirror') {
            mkdir($package . DIRECTORY_SEPARATOR . 'bin', 0777, true);
            foreach (['durin', 'durins-forge', 'bootstrap.php'] as $file) {
                copy(
                    $checkout . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $file,
                    $package . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $file,
                );
            }
            copy($checkout . DIRECTORY_SEPARATOR . 'composer.json', $package . DIRECTORY_SEPARATOR . 'composer.json');

            return;
        }

        if ($mode === 'junction') {
            $command = 'cmd.exe /C mklink /J ' . escapeshellarg($package) . ' ' . escapeshellarg($checkout);
            exec($command, $output, $status);
            if ($status !== 0 || !is_dir($package)) {
                self::markTestSkipped('mklink /J is not available: ' . implode(' ', $output));
            }

            return;
        }

        if (!@symlink($checkout, $package)) {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->placePackage($checkout, $package, 'junction');

                return;
            }
            self::markTestSkipped('symlink() is not available.');
        }
    }

    /**
     * @param list<string>|null $relativeFiles
     * @return array<string, string>
     */
    private function hashes(string $root, ?array $relativeFiles = null): array
    {
        $paths = $relativeFiles ?? $this->filesUnder($root);
        $hashes = [];
        foreach ($paths as $relative) {
            $absolute = $relativeFiles === null
                ? $relative
                : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($absolute)) {
                continue;
            }
            $key = $relativeFiles === null
                ? ltrim(str_replace('\\', '/', substr($absolute, strlen($root))), '/')
                : $relative;
            $hashes[$key] = hash_file('sha256', $absolute) ?: '';
        }
        ksort($hashes);

        return $hashes;
    }

    /**
     * @return list<string>
     */
    private function filesUnder(string $root): array
    {
        if (!is_dir($root) || $this->isLink($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($this->isLink($path) || !$file->isFile()) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);

        return $files;
    }

    private function marker(array $layout): string
    {
        return is_file($layout['marker']) ? (string) file_get_contents($layout['marker']) : '';
    }

    private function assertRealPackageUnchanged(): void
    {
        self::assertSame($this->realHashes, $this->hashes($this->realPackage, array_keys($this->realHashes)));
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function launch(array $command, string $cwd): array
    {
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true],
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout ?: '', $stderr ?: ''];
    }

    private function isLink(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }
        if (PHP_OS_FAMILY !== 'Windows' || !is_dir($path)) {
            return false;
        }
        $real = realpath($path);
        if ($real === false) {
            return false;
        }
        $left = rtrim(strtolower(str_replace('\\', '/', $path)), '/');
        $right = rtrim(strtolower(str_replace('\\', '/', $real)), '/');

        return $left !== $right;
    }

    private function removeTree(string $path): void
    {
        if ($this->isLink($path)) {
            if (is_link($path)) {
                unlink($path);
            } else {
                rmdir($path);
            }

            return;
        }
        if (!is_dir($path)) {
            @unlink($path);
            @rmdir($path);

            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
        rmdir($path);
    }
}
