<?php

declare(strict_types=1);

namespace App\Tooling\Project;

final class ProjectDiscovery
{
    public function __construct(
        private readonly DurinManifestParser $manifestParser = new DurinManifestParser(),
    ) {}

    public function discover(?string $startDirectory = null): Project
    {
        $root = $this->locateRoot($startDirectory ?? getcwd() ?: base_path());
        $paths = new ProjectPaths($root);

        $manifest = null;
        if (is_file($paths->durinYaml())) {
            $manifest = $this->manifestParser->parseFile($paths->durinYaml());
        }

        return new Project($paths, $manifest);
    }

    public function locateRoot(string $startDirectory): string
    {
        $dir = realpath($startDirectory) ?: $startDirectory;

        while (true) {
            if ($this->looksLikeProjectRoot($dir)) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new DurinManifestException(
            "Unable to locate a Durin project root from {$startDirectory} (expected composer.json or durin.yaml)."
        );
    }

    private function looksLikeProjectRoot(string $dir): bool
    {
        return is_file($dir . DIRECTORY_SEPARATOR . 'durin.yaml')
            || is_file($dir . DIRECTORY_SEPARATOR . 'composer.json');
    }
}
