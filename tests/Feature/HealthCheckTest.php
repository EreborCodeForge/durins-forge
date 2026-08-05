<?php

namespace App\Tests\Feature;

use App\Tests\DurinsForgeBaseTest;
use Erebor\Mithril\Http\Request;

class HealthCheckTest extends DurinsForgeBaseTest
{
    public function test_health_check_endpoint_returns_ok()
    {
        // Simulate a GET request to /api/health
        $request = new Request(
            [], // query
            [], // body
            ['REQUEST_URI' => '/api/health', 'REQUEST_METHOD' => 'GET'], // server
            [], // headers
            [], // cookies
            []  // files
        );

        $response = $this->kernel->handle($request);

        if ($response->getStatusCode() !== 200) {
            echo "\nResponse: " . $response->getContent() . "\n";
        }

        $this->assertEquals(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('ok', $content['status']);
    }
}
