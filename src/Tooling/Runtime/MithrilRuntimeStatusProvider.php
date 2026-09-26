<?php

declare(strict_types=1);

namespace App\Tooling\Runtime;

use App\Tooling\Project\DurinManifestException;
use App\Tooling\Project\DurinManifestParser;
use Erebor\Mithril\Runtime\Eregion\ApplicationResolver;

/**
 * Reads local runtime metadata via Mithril resolvers + config/manifest files.
 * Does not invent live worker metrics when Eregion ops API is unavailable (V1).
 */
final class MithrilRuntimeStatusProvider implements RuntimeStatusProvider
{
    public function __construct(
        private readonly EregionBinaryLocator $binaryLocator = new MithrilEregionBinaryLocator(),
    ) {}

    public function read(RuntimeOptions $options): RuntimeStatus
    {
        $root = $options->workingDirectory;
        $resolver = new ApplicationResolver($root);
        $binary = $this->binaryLocator->resolve($root);
        $config = $resolver->eregionConfigPath();
        $manifest = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'eregion.json';

        $details = [
            'eregion_config_present' => is_file($config),
            'manifest_present' => is_file($manifest),
            'worker_present' => is_file($resolver->eregionWorkerPath()),
            'protocol' => 'eregion/1',
            'live_metrics' => 'unavailable_in_v1',
        ];

        if (is_file($config)) {
            $details = array_merge($details, $this->readConfigDetails($config));
        }

        if (is_file($manifest)) {
            $details = array_merge($details, $this->readManifestDetails($manifest));
        }

        if ($binary === null) {
            return new RuntimeStatus(
                available: false,
                summary: 'Eregion binary not found (run forge server:install). Runtime is not ready.',
                binaryPath: null,
                manifestPath: is_file($manifest) ? $manifest : null,
                configPath: is_file($config) ? $config : null,
                details: $details,
            );
        }

        $summary = is_file($manifest)
            ? 'Runtime tooling and manifest present (live process metrics not queried in V1)'
            : 'Runtime tooling available; manifest missing (run forge eregion:craft / serve once)';

        return new RuntimeStatus(
            available: true,
            summary: $summary,
            binaryPath: $binary,
            manifestPath: is_file($manifest) ? $manifest : null,
            configPath: is_file($config) ? $config : null,
            details: $details,
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function readConfigDetails(string $configPath): array
    {
        try {
            $data = DurinManifestParser::decode((string) file_get_contents($configPath));
        } catch (DurinManifestException) {
            return ['config_parse' => 'failed'];
        }

        $server = is_array($data['server'] ?? null) ? $data['server'] : [];
        $workers = is_array($data['workers'] ?? null) ? $data['workers'] : [];

        return [
            'http_host' => isset($server['host']) && is_scalar($server['host']) ? (string) $server['host'] : null,
            'http_port' => isset($server['port']) && is_numeric($server['port']) ? (int) $server['port'] : null,
            'workers_configured' => isset($workers['count']) && is_numeric($workers['count']) ? (int) $workers['count'] : null,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    private function readManifestDetails(string $manifestPath): array
    {
        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($decoded)) {
            return ['manifest_parse' => 'failed'];
        }

        $protocol = is_array($decoded['protocol'] ?? null) ? $decoded['protocol'] : [];
        $version = $protocol['version'] ?? null;

        return [
            'manifest_application' => isset($decoded['application']) && is_string($decoded['application'])
                ? $decoded['application']
                : null,
            'manifest_environment' => isset($decoded['environment']) && is_string($decoded['environment'])
                ? $decoded['environment']
                : null,
            'protocol_version' => is_numeric($version) ? (int) $version : null,
        ];
    }
}
