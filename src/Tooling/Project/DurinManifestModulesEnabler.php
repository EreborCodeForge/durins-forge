<?php

declare(strict_types=1);

namespace App\Tooling\Project;

/**
 * Enables architecture.modules in durin.yaml when present.
 * Used after make:module succeeds (managed metadata update, not a free-form overwrite).
 */
final class DurinManifestModulesEnabler
{
    public function __construct(
        private readonly DurinManifestParser $parser = new DurinManifestParser(),
    ) {}

    public function enable(string $projectRoot): bool
    {
        $path = rtrim($projectRoot, "/\\") . DIRECTORY_SEPARATOR . 'durin.yaml';
        if (!is_file($path)) {
            return false;
        }

        $manifest = $this->parser->parseFile($path);
        if ($manifest->architecture['modules'] === true) {
            return false;
        }

        $data = $manifest->toArray();
        $data['architecture']['modules'] = true;
        $updated = DurinManifest::fromArray($data)->toYaml();

        if (file_put_contents($path, $updated) === false) {
            throw new DurinManifestException("Unable to write {$path}");
        }

        return true;
    }
}
