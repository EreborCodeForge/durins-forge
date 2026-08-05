<?php

declare(strict_types=1);

namespace App\Infrastructure\Bridge;

use App\Infrastructure\Session\SessionManager;
use Erebor\Mithril\Container;
use Erebor\Mithril\Environment;

final class VueViewHandlerBuilder
{
    public static function build(Container $c): VueViewHandler
    {
        $vue = new VueViewHandler();
        $session = $c->get(SessionManager::class);
        $vue->share('appName', Environment::get('APP_NAME', 'Durins Forge'));
        $vue->share('csrf_token', $session->csrfToken());
        $vue->share('flash', $session->getAllFlash());
        $vue->share('auth', ['user' => $session->get('user')]);

        return $vue;
    }
}
