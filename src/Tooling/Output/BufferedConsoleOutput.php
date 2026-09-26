<?php

declare(strict_types=1);

namespace App\Tooling\Output;

final class BufferedConsoleOutput implements ConsoleOutput
{
    /** @var list<array{level: string, message: string}> */
    private array $entries = [];

    public function line(string $message): void
    {
        $this->entries[] = ['level' => 'line', 'message' => $message];
    }

    public function info(string $message): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message];
    }

    public function warning(string $message): void
    {
        $this->entries[] = ['level' => 'warning', 'message' => $message];
    }

    public function error(string $message): void
    {
        $this->entries[] = ['level' => 'error', 'message' => $message];
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(
            static fn (array $entry): string => $entry['message'],
            $this->entries
        );
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
