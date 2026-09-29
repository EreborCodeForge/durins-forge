<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Init;

/**
 * Merges an existing neutral .env.example with preset overlay keys.
 * Existing values win; planned keys missing from the base are appended.
 */
final class EnvExampleMerger
{
    public function merge(string $existing, string $planned): string
    {
        $base = $this->parse($existing);
        $overlay = $this->parse($planned);

        foreach ($overlay as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
            }
        }

        return $this->dump($base);
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $contents): array
    {
        $vars = [];
        foreach (preg_split("/\r\n|\n|\r/", $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (!str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $vars[$key] = $value;
        }

        return $vars;
    }

    /**
     * @param array<string, string> $vars
     */
    private function dump(array $vars): string
    {
        $lines = [];
        foreach ($vars as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        return implode("\n", $lines) . (count($lines) > 0 ? "\n" : '');
    }
}
