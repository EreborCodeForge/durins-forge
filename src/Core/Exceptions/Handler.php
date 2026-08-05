<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Erebor\Mithril\Http\Response;
use Throwable;
use Erebor\Mithril\Environment;

class Handler
{
    public function handle(Throwable $e): void
    {
        $code = $e->getCode();
        if ($code < 100 || $code > 599) {
            $code = 500;
        }

        // Use standard response
        $response = new Response();
        $response->setStatusCode($code);
        
        if (Environment::get('APP_DEBUG') === 'true') {
            $content = "<h1>Fatal Error</h1>";
            $content .= "<p>" . $e->getMessage() . "</p>";
            $content .= "<pre>" . $e->getTraceAsString() . "</pre>";
            $response->setContent($content);
        } else {
            $response->setContent('Internal Server Error');
        }

        $response->send();
    }
}
