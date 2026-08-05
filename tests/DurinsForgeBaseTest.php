<?php

namespace App\Tests;

use App\Kernel;
use Erebor\Mithril\Container;
use PHPUnit\Framework\TestCase;

abstract class DurinsForgeBaseTest extends TestCase
{
    protected Kernel $kernel;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    protected function createApplication(): void
    {
        $this->kernel = new Kernel();
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
    }
}
