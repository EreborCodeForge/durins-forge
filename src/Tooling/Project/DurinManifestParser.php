<?php

declare(strict_types=1);

namespace App\Tooling\Project;

/**
 * Minimal YAML subset for durin.yaml (maps, scalars, 2-space indent).
 * Not a general-purpose YAML implementation.
 */
final class DurinManifestParser
{
    public function parseFile(string $path): DurinManifest
    {
        if (!is_file($path)) {
            throw new DurinManifestException("Manifest not found: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new DurinManifestException("Unable to read manifest: {$path}");
        }

        return $this->parse($contents);
    }

    public function parse(string $yaml): DurinManifest
    {
        return DurinManifest::fromArray(self::decode($yaml));
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $yaml): array
    {
        $lines = preg_split('/\R/', $yaml) ?: [];
        /** @var list<array{indent: int, map: array<string, mixed>}> $frames */
        $frames = [
            ['indent' => -1, 'map' => []],
        ];

        foreach ($lines as $lineNumber => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!preg_match('/^( *)([^:#]+):\s*(.*)$/', $line, $matches)) {
                throw new DurinManifestException('Invalid YAML line ' . ($lineNumber + 1) . ': ' . $line);
            }

            $indent = strlen($matches[1]);
            $key = trim($matches[2]);
            $rawValue = $matches[3];

            while (count($frames) > 1 && $indent <= $frames[array_key_last($frames)]['indent']) {
                $child = array_pop($frames);
                $frames[array_key_last($frames)]['map'][$child['key']] = $child['map'];
            }

            $parentIndex = array_key_last($frames);

            if ($rawValue === '') {
                $frames[] = [
                    'indent' => $indent,
                    'key' => $key,
                    'map' => [],
                ];
                continue;
            }

            $frames[$parentIndex]['map'][$key] = self::decodeScalar(trim($rawValue));
        }

        while (count($frames) > 1) {
            $child = array_pop($frames);
            $frames[array_key_last($frames)]['map'][$child['key']] = $child['map'];
        }

        return $frames[0]['map'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function dump(array $data, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        $out = '';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out .= $pad . $key . ":\n";
                $out .= self::dump($value, $indent + 1);
                continue;
            }

            $out .= $pad . $key . ': ' . self::encodeScalar($value) . "\n";
        }

        return $out;
    }

    private static function decodeScalar(string $value): mixed
    {
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null' || $value === '~') {
            return null;
        }
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function encodeScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        if (preg_match('/[\s:#]/', $string) === 1) {
            return '"' . addcslashes($string, "\"\\") . '"';
        }

        return $string;
    }
}
