<?php

namespace App\Tests\Feature;

use App\Tests\DurinsForgeBaseTest;
use Erebor\Mithril\Http\Request;

class AuthTest extends DurinsForgeBaseTest
{
    public function test_register_creates_new_user()
    {
        // Mock data
        $email = 'test_' . uniqid() . '@example.com';
        $payload = [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password123'
        ];

        $request = new Request(
            [], // query
            $payload, // body
            ['REQUEST_URI' => '/api/register', 'REQUEST_METHOD' => 'POST'], // server
            ['CONTENT_TYPE' => 'application/json'], // headers
            [], // cookies
            [] // files
        );

        $response = $this->kernel->handle($request);
        $content = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 201) {
            echo "\nError Response: " . $response->getContent() . "\n";
        }

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('User created successfully', $content['message']);
        $this->assertArrayHasKey('user', $content);
        $this->assertEquals($email, $content['user']['email']);
    }

    public function test_login_returns_token()
    {
        // First register a user
        $email = 'login_' . uniqid() . '@example.com';
        $registerPayload = [
            'name' => 'Login User',
            'email' => $email,
            'password' => 'password123'
        ];

        $registerRequest = new Request(
            [], // query
            $registerPayload, // body
            ['REQUEST_URI' => '/api/register', 'REQUEST_METHOD' => 'POST'], // server
            ['CONTENT_TYPE' => 'application/json'], // headers
            [], // cookies
            [] // files
        );
        $this->kernel->handle($registerRequest);

        // Now try to login
        $loginPayload = [
            'email' => $email,
            'password' => 'password123'
        ];

        $request = new Request(
            [], // query
            $loginPayload, // body
            ['REQUEST_URI' => '/api/login', 'REQUEST_METHOD' => 'POST'], // server
            ['CONTENT_TYPE' => 'application/json'], // headers
            [], // cookies
            [] // files
        );

        $response = $this->kernel->handle($request);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('token', $content);
        $this->assertArrayHasKey('user', $content);
    }
}
