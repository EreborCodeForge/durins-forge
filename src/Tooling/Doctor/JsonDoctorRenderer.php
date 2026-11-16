<?php

declare(strict_types=1);

namespace EreborCodeForge\Durin\Forge\Tooling\Doctor;

final class JsonDoctorRenderer implements DoctorRenderer
{
    public function render(DoctorReport $report): string
    {
        $payload = $report->toArray();
        $payload['runtime'] = $this->runtime($report);

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
    }

    /**
     * Stable operational fields. Values are check details, never environment contents.
     *
     * @return array{pin: string|null, binary: string|null, workload: string|null, mode: string|null, metrics_access: string|null}
     */
    private function runtime(DoctorReport $report): array
    {
        $runtime = [
            'pin' => null,
            'binary' => null,
            'workload' => null,
            'mode' => null,
            'metrics_access' => null,
        ];
        foreach ($report->results as $result) {
            match ($result->id) {
                'runtime.eregion.pin' => $runtime['pin'] = $result->detail,
                'runtime.eregion.binary_path' => $runtime['binary'] = $result->detail,
                'runtime.eregion.consumer_workload', 'runtime.job.consumer_workload' => $runtime['workload'] = $result->detail,
                'runtime.job.supervision' => $runtime['mode'] = $result->detail,
                'runtime.eregion.metrics_access' => $runtime['metrics_access'] = $result->detail,
                default => null,
            };
        }

        return $runtime;
    }
}
