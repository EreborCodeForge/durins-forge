<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Http\Controllers;

use Erebor\Mithril\Http\Response;

class HealthCheckController
{
    public function check(): Response
    {
        return (new Response())->json([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'Durin\'s Forge',
            'version' => 'core',
        ]);
    }
}
