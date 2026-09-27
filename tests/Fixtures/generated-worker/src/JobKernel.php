<?php

declare(strict_types=1);

namespace App;

use Erebor\Mithril\Container;
use Erebor\Mithril\Contracts\JobApplication;
use Erebor\Mithril\Jobs\InMemoryJobTransport;
use Erebor\Mithril\Jobs\JobEnvelope;
use Erebor\Mithril\Jobs\JobResult;
use Erebor\Mithril\Jobs\JobTransport;

/**
 * Job application kernel for fixture-worker.
 * Bind a real JobTransport adapter for production brokers.
 */
final class JobKernel implements JobApplication
{
    private Container $container;
    private bool $booted = false;

    public function __construct()
    {
        $this->container = new Container();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Demo transport — replace with Infrastructure adapter (Redis/SQS/…).
        $this->container->singleton(JobTransport::class, new InMemoryJobTransport([]));

        $this->booted = true;
    }

    public function handle(JobEnvelope $job): JobResult
    {
        // Dispatch by $job->name into src/Jobs handlers.
        return JobResult::ack();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
