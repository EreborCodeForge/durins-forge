<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Unit\Tooling\Output;

use EreborCodeForge\Durin\Core\Output\BufferedConsoleOutput;
use PHPUnit\Framework\TestCase;

final class BufferedConsoleOutputTest extends TestCase
{
    public function test_records_levels(): void
    {
        $out = new BufferedConsoleOutput();
        $out->line('a');
        $out->info('b');
        $out->warning('c');
        $out->error('d');

        $this->assertSame(['a', 'b', 'c', 'd'], $out->messages());
        $this->assertSame('warning', $out->entries()[2]['level']);
    }
}
