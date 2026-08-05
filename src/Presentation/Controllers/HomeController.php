<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Bridge\VueViewHandler;
use Erebor\Mithril\Http\HttpContext;
use Erebor\Mithril\Http\Response;

class HomeController
{
    public function __construct(
        private VueViewHandler $vue
    ) {}

    public function index(HttpContext $context): Response
    {
        return $this->vue->render('Welcome', [
            'message' => 'The Forge is Ready.'
        ]);
    }
}
