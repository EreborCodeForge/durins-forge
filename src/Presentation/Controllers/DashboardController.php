<?php

namespace App\Presentation\Controllers;

use App\Infrastructure\Bridge\VueViewHandler;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class DashboardController
{
    public function __construct(
        private VueViewHandler $vue
    ) {}

    public function index(HttpContext $context): Response
    {
        return $this->vue->render('Dashboard', [
            'user' => [
                'name' => 'Alan',
                'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=500&q=60',
            ]
        ]);
    }

    public function login(HttpContext $context): Response
    {
        return $this->vue->render('Login');
    }
}
