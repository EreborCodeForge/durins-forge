<?php

declare(strict_types=1);

namespace App\Tooling\Project;

/**
 * Minimal durin.yaml model (master §22).
 */
final readonly class DurinManifest
{
    /**
     * @param array{http: bool, messaging: bool} $features
     * @param array{modules: bool} $architecture
     */
    public function __construct(
        public string $applicationName,
        public string $preset,
        public string $runtimeEngine,
        public string $runtimeServer,
        public array $features,
        public array $architecture,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $application = $data['application'] ?? null;
        if (!is_array($application)) {
            throw new DurinManifestException('durin.yaml requires application section.');
        }

        $name = $application['name'] ?? null;
        $preset = $application['preset'] ?? null;
        if (!is_string($name) || $name === '' || !is_string($preset) || $preset === '') {
            throw new DurinManifestException('application.name and application.preset are required strings.');
        }

        $runtime = $data['runtime'] ?? [];
        if ($runtime !== [] && !is_array($runtime)) {
            throw new DurinManifestException('runtime must be a mapping.');
        }
        $runtime = is_array($runtime) ? $runtime : [];

        $engine = $runtime['engine'] ?? 'mithril';
        $server = $runtime['server'] ?? 'eregion';
        if (!is_string($engine) || $engine === '' || !is_string($server) || $server === '') {
            throw new DurinManifestException('runtime.engine and runtime.server must be non-empty strings.');
        }

        $features = $data['features'] ?? ['http' => true, 'messaging' => false];
        if (!is_array($features)) {
            throw new DurinManifestException('features must be a mapping.');
        }

        $architecture = $data['architecture'] ?? ['modules' => false];
        if (!is_array($architecture)) {
            throw new DurinManifestException('architecture must be a mapping.');
        }

        return new self(
            applicationName: $name,
            preset: $preset,
            runtimeEngine: $engine,
            runtimeServer: $server,
            features: [
                'http' => (bool) ($features['http'] ?? true),
                'messaging' => (bool) ($features['messaging'] ?? false),
            ],
            architecture: [
                'modules' => (bool) ($architecture['modules'] ?? false),
            ],
        );
    }

    /**
     * @return array{
     *   application: array{name: string, preset: string},
     *   runtime: array{engine: string, server: string},
     *   features: array{http: bool, messaging: bool},
     *   architecture: array{modules: bool}
     * }
     */
    public function toArray(): array
    {
        return [
            'application' => [
                'name' => $this->applicationName,
                'preset' => $this->preset,
            ],
            'runtime' => [
                'engine' => $this->runtimeEngine,
                'server' => $this->runtimeServer,
            ],
            'features' => $this->features,
            'architecture' => $this->architecture,
        ];
    }

    public function toYaml(): string
    {
        return DurinManifestParser::dump($this->toArray());
    }
}
