<?php

declare(strict_types=1);

namespace App\Tooling\Support;

/**
 * Generic success/failure carrier for tooling operations.
 *
 * @template T
 */
final readonly class OperationResult
{
    /**
     * @param T|null $value
     * @param list<string> $messages
     */
    private function __construct(
        public bool $ok,
        public mixed $value,
        public array $messages,
    ) {}

    /**
     * @template TValue
     * @param TValue $value
     * @param list<string> $messages
     * @return self<TValue>
     */
    public static function success(mixed $value = null, array $messages = []): self
    {
        return new self(true, $value, $messages);
    }

    /**
     * @param list<string> $messages
     * @return self<null>
     */
    public static function failure(array $messages): self
    {
        return new self(false, null, $messages);
    }

    public function message(): string
    {
        return implode("\n", $this->messages);
    }
}
