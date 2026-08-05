<?php

namespace App\Tests\Unit\Infrastructure\Providers;

use App\Infrastructure\Bridge\VueViewHandler;
use App\Tests\DurinsForgeBaseTest;
use Erebor\Mithril\Environment;

class ViewServiceProviderTest extends DurinsForgeBaseTest
{
    public function test_it_registers_vue_view_handler_as_singleton_with_default_shared_props(): void
    {
        $stubUser = ['id' => 123, 'name' => 'Test User'];
        $stubFlash = ['success' => 'Saved'];
        $stubCsrf = 'csrf_test_token';

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $_SESSION['user'] = $stubUser;
        $_SESSION['flash'] = $stubFlash;
        $_SESSION['csrf_token'] = $stubCsrf;

        $handlerA = $this->container->get(VueViewHandler::class);
        $handlerB = $this->container->get(VueViewHandler::class);

        $this->assertSame($handlerA, $handlerB);

        $_SERVER['HTTP_X_DURINS_FORGE_VUE'] = 'true';
        $_SERVER['REQUEST_URI'] = '/provider-test';

        $response = $handlerA->render('TestComponent', ['local' => 'prop']);
        $content = json_decode($response->getContent(), true);

        $this->assertSame('TestComponent', $content['component']);
        $this->assertSame('/provider-test', $content['url']);

        $props = $content['props'];
        $this->assertSame(Environment::get('APP_NAME', 'Durins Forge'), $props['appName']);
        $this->assertSame($stubCsrf, $props['csrf_token']);
        $this->assertSame($stubFlash, $props['flash']);
        $this->assertSame($stubUser, $props['auth']['user']);
        $this->assertSame('prop', $props['local']);

        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        unset($_SERVER['HTTP_X_DURINS_FORGE_VUE'], $_SERVER['REQUEST_URI']);
    }
}
