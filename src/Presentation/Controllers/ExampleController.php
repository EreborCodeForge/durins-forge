<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use App\Infrastructure\Bridge\VueViewHandler;
use Erebor\Mithril\Http\Request;
use Erebor\Mithril\Http\Response;

class ExampleController
{
    public function __construct(
        private VueViewHandler $vue
    ) {}

    public function index(Request $request): Response
    {
        return $this->vue->render('Example/Dashboard', [
            'stats' => [
                ['label' => 'Total Users', 'value' => '1,234', 'change' => '+12%', 'color' => 'bg-emerald-500'],
                ['label' => 'Revenue', 'value' => '$45,678', 'change' => '+5.4%', 'color' => 'bg-blue-500'],
                ['label' => 'Active Sessions', 'value' => '892', 'change' => '-2%', 'color' => 'bg-amber-500'],
            ]
        ]);
    }
}
