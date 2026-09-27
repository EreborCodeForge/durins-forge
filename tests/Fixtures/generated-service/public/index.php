<?php

declare(strict_types=1);

// Worker / HTTP entry placeholder for a Service Durin app.
// Replace with Mithril HttpApplication bootstrap when wiring runtime.

require dirname(__DIR__) . '/vendor/autoload.php';

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "Service Durin app: configure Kernel + Eregion worker entry before serving.\n";
