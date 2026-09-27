<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

final readonly class CheckResult
{
    /**
     * @param DoctorExitCode::*|null $failureCode exit hint when status is Fail
     */
    public function __construct(
        public string $id,
        public string $label,
        public CheckStatus $status,
        public string $detail,
        public ?int $failureCode = null,
    ) {}

    public static function ok(string $id, string $label, string $detail): self
    {
        return new self($id, $label, CheckStatus::Ok, $detail);
    }

    public static function warning(string $id, string $label, string $detail): self
    {
        return new self($id, $label, CheckStatus::Warning, $detail);
    }

    public static function fail(
        string $id,
        string $label,
        string $detail,
        int $failureCode = DoctorExitCode::InvalidConfiguration,
    ): self {
        return new self($id, $label, CheckStatus::Fail, $detail, $failureCode);
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string, failure_code: int|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status->value,
            'detail' => $this->detail,
            'failure_code' => $this->failureCode,
        ];
    }
}
