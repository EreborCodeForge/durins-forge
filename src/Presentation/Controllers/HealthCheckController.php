<?php

declare(strict_types=1);

namespace App\Presentation\Controllers;

use Erebor\Mithril\Http\Response;

class HealthCheckController
{
    public function check(): Response
    {
        return (new Response())->json([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'Durin\'s Forge API',
            'version' => '1.0.2'
        ]);
    }
}
