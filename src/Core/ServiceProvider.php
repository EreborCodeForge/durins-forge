<?php

declare(strict_types=1);

namespace App\Core;

use Erebor\Mithril\Container;

interface ServiceProvider
{
    public function register(Container $c): void;
    public function describe(): array;
}
