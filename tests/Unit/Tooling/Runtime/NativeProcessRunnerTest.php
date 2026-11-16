<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Runtime;

use EreborCodeForge\Durin\Forge\Tooling\Runtime\NativeProcessRunner;
use PHPUnit\Framework\TestCase;

final class NativeProcessRunnerTest extends TestCase
{
    public function test_runs_the_binary_without_prefixing_php(): void
    {
        $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'native_runner_' . uniqid('', true) . '.txt';
        $code = (new NativeProcessRunner())->run(
            PHP_BINARY,
            ['-r', 'file_put_contents(' . var_export($marker, true) . ', PHP_BINARY);'],
            sys_get_temp_dir(),
        );

        try {
            $this->assertSame(0, $code);
            $this->assertSame(PHP_BINARY, file_get_contents($marker));
        } finally {
            if (is_file($marker)) {
                unlink($marker);
            }
        }
    }
}
