<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Runtime;

use EreborCodeForge\Durin\Presets\Metadata\RuntimeProfile;

/**
 * Normalizes preset RuntimeProfile into capability requirements (Forge-owned).
 *
 * requiredCapabilities are hard filters; preferredCapabilities are ranking hints only.
 *
 * @param list<string> $capabilities
 * @param list<string> $preferredCapabilities
 */
final readonly class RuntimeRequirements
{
    /**
     * @param list<string> $capabilities
     * @param list<string> $preferredCapabilities
     */
    public function __construct(
        public array $capabilities,
        public string $mode,
        public ?string $preferredRunner,
        public array $preferredCapabilities = [],
    ) {}

    public static function fromProfile(RuntimeProfile $profile): self
    {
        $mode = match ($profile->mode) {
            'worker', 'job' => 'job',
            'http' => 'http',
            default => $profile->mode,
        };

        $caps = array_values($profile->requiredCapabilities);
        if ($caps === []) {
            $caps = match ($mode) {
                'http' => ['persistent-http'],
                'job' => ['job-loop', 'messaging'],
                default => throw new RuntimeResolutionException(
                    "Unsupported runtime mode \"{$profile->mode}\": cannot derive required capabilities."
                ),
            };
        } else {
            $caps = self::expandAliases($caps, $mode);
        }

        return new self(
            $caps,
            $mode,
            $profile->preferredRunner,
            array_values(array_unique($profile->preferredCapabilities)),
        );
    }

    /**
     * @param list<string> $provided
     */
    public function isCoveredBy(array $provided): bool
    {
        foreach ($this->capabilities as $cap) {
            if (!in_array($cap, $provided, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ranking score: how many preferredCapabilities the candidate provides.
     * Never used as a hard filter.
     *
     * @param list<string> $provided
     */
    public function preferenceScore(array $provided): int
    {
        if ($this->preferredCapabilities === []) {
            return 0;
        }

        $score = 0;
        foreach ($this->preferredCapabilities as $cap) {
            if (in_array($cap, $provided, true)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * @param list<string> $caps
     * @return list<string>
     */
    private static function expandAliases(array $caps, string $mode): array
    {
        $expanded = [];
        foreach ($caps as $cap) {
            if ($cap === 'http') {
                $expanded[] = 'persistent-http';
                continue;
            }
            $expanded[] = $cap;
        }

        if (
            $mode === 'job'
            && in_array('messaging', $expanded, true)
            && !in_array('job-loop', $expanded, true)
        ) {
            $expanded[] = 'job-loop';
        }

        return array_values(array_unique($expanded));
    }
}
