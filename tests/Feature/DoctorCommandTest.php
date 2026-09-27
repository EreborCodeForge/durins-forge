<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tests\Feature;

use EreborCodeForge\Durin\Forge\Console\Application;
use PHPUnit\Framework\TestCase;

final class DoctorCommandTest extends TestCase
{
    public function test_doctor_json_emits_report_payload(): void
    {
        $argv = ['durin', 'doctor', '--json'];
        ob_start();
        $code = (new Application())->run($argv);
        $output = (string) ob_get_clean();

        $this->assertMatchesRegularExpression('/\{.*"summary".*\}/s', $output);
        preg_match('/\{.*\}/s', $output, $matches);
        $this->assertNotEmpty($matches[0] ?? null);

        $data = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('exit_code', $data);
        $this->assertIsArray($data['checks']);
        $this->assertNotEmpty($data['checks']);

        // Exit code from doctor must match payload (logo may precede JSON).
        $this->assertSame($data['exit_code'], $code);
    }
}
