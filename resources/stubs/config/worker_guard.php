<?php

declare(strict_types=1);

return [
    'mode' => getenv('WORKER_GUARD_MODE') ?: 'off',
    'max_probes_per_cycle' => 8,
    'sample_every_n_requests' => 10,
    'report_path' => 'var/worker-guard',
    'policy' => [
        'recycle_on_verified_state_violation' => false,
        'recycle_on_suspected_memory_growth' => false,
    ],
    'static_watchpoints' => [
        // ['class' => App\Security\UserContext::class, 'property' => 'userId', 'lifetime' => 'request'],
    ],
];
