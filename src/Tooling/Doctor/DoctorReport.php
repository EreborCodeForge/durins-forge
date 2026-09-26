<?php

declare(strict_types=1);

namespace App\Tooling\Doctor;

final class DoctorReport
{
    /**
     * @param list<CheckResult> $results
     */
    public function __construct(
        public readonly array $results,
        public readonly bool $strict = false,
    ) {}

    public function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === CheckStatus::Fail) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === CheckStatus::Warning) {
                return true;
            }
        }

        return false;
    }

    public function summary(): string
    {
        if ($this->hasFailures()) {
            return 'unhealthy';
        }
        if ($this->hasWarnings()) {
            return $this->strict ? 'warnings (strict)' : 'healthy with warnings';
        }

        return 'healthy';
    }

    public function exitCode(): int
    {
        $failureCode = null;
        foreach ($this->results as $result) {
            if ($result->status !== CheckStatus::Fail) {
                continue;
            }
            $code = $result->failureCode ?? DoctorExitCode::InvalidConfiguration;
            $failureCode = $failureCode === null ? $code : max($failureCode, $code);
        }

        if ($failureCode !== null) {
            return $failureCode;
        }

        if ($this->strict && $this->hasWarnings()) {
            return DoctorExitCode::StrictWarnings;
        }

        return DoctorExitCode::Healthy;
    }

    /**
     * @return array{summary: string, strict: bool, exit_code: int, checks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'strict' => $this->strict,
            'exit_code' => $this->exitCode(),
            'checks' => array_map(
                static fn (CheckResult $result): array => $result->toArray(),
                $this->results
            ),
        ];
    }
}
