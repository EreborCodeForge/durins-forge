<?php

namespace App\Tests\Unit\Infrastructure\Bridge;

use App\Infrastructure\Bridge\VueViewHandler;
use App\Tests\DurinsForgeBaseTest;
use Erebor\Mithril\Http\Response;

class VueViewHandlerTest extends DurinsForgeBaseTest
{
    private VueViewHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        // Resolve from container to test ServiceProvider integration
        $this->handler = $this->container->get(VueViewHandler::class);
    }

    public function test_it_resolves_from_container_with_default_shared_props()
    {
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('sharedProps');
        $property->setAccessible(true);
        $props = $property->getValue($this->handler);

        // Check if default props from ViewServiceProvider are present
        $this->assertArrayHasKey('appName', $props);
        $this->assertArrayHasKey('csrf_token', $props);
        $this->assertArrayHasKey('flash', $props);
        $this->assertArrayHasKey('auth', $props);
    }

    public function test_it_shares_props()
    {
        $this->handler->share('app_name', 'Durin\'s Forge');
        $this->handler->share('user', ['id' => 1]);

        // Access private property via reflection to verify
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('sharedProps');
        $property->setAccessible(true);
        $props = $property->getValue($this->handler);

        $this->assertArrayHasKey('app_name', $props);
        $this->assertEquals('Durin\'s Forge', $props['app_name']);
        $this->assertEquals(['id' => 1], $props['user']);
    }

    public function test_render_returns_json_when_bridge_header_present()
    {
        $_SERVER['HTTP_X_DURINS_FORGE_VUE'] = 'true';
        $_SERVER['REQUEST_URI'] = '/test-url';

        $this->handler->share('shared', 'value');
        $response = $this->handler->render('TestComponent', ['local' => 'prop']);

        $this->assertInstanceOf(Response::class, $response);
        
        // Mithril Response implementation details might vary, assuming getContent() returns string
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('TestComponent', $content['component']);
        $this->assertEquals('/test-url', $content['url']);
        $this->assertEquals('value', $content['props']['shared']);
        $this->assertEquals('prop', $content['props']['local']);

        unset($_SERVER['HTTP_X_DURINS_FORGE_VUE']);
        unset($_SERVER['REQUEST_URI']);
    }

    public function test_render_returns_html_when_bridge_header_absent()
    {
        // Ensure header is unset
        if (isset($_SERVER['HTTP_X_DURINS_FORGE_VUE'])) {
            unset($_SERVER['HTTP_X_DURINS_FORGE_VUE']);
        }
        $_SERVER['REQUEST_URI'] = '/html-url';

        $this->handler->share('shared', 'value');
        $response = $this->handler->render('TestComponent', ['local' => 'prop']);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();

        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('id="app"', $content);
        $this->assertStringContainsString('data-page=', $content);
        
        // Decode the data-page attribute to verify props
        preg_match("/data-page='(.*?)'/", $content, $matches);
        $this->assertNotEmpty($matches);
        
        $pageData = json_decode($matches[1], true);
        $this->assertEquals('TestComponent', $pageData['component']);
        $this->assertEquals('/html-url', $pageData['url']);
        $this->assertEquals('value', $pageData['props']['shared']);
        $this->assertEquals('prop', $pageData['props']['local']);
        $this->assertEquals('1.0.0', $pageData['version']);

        unset($_SERVER['REQUEST_URI']);
    }
}
