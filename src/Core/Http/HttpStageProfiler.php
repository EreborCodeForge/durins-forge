<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Core\Http;

/**
 * Opt-in HTTP stage timings. Disabled builds call the work and keep no samples.
 */
final class HttpStageProfiler
{
    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, int> */
    private array $totals = [];

    public function __construct(
        private readonly bool $enabled = false,
    ) {}

    public static function fromEnvironment(): self
    {
        $flag = getenv('MITHRIL_PROFILE');

        return new self(is_string($flag) && $flag === '1');
    }

    public function measure(string $stage, callable $work): mixed
    {
        if (!$this->enabled) {
            return $work();
        }

        $started = hrtime(true);
        try {
            return $work();
        } finally {
            $this->counts[$stage] = ($this->counts[$stage] ?? 0) + 1;
            $this->totals[$stage] = ($this->totals[$stage] ?? 0) + (hrtime(true) - $started);
        }
    }

    /**
     * @return array<string, int> executions per stage; empty when profiling is off
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return array<string, int> nanoseconds per stage; empty when profiling is off
     */
    public function totals(): array
    {
        return $this->totals;
    }
}
