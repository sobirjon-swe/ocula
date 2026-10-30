<?php

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'optika_horizon:'),
    'middleware' => ['web'],
    'waits' => ['redis:default' => 60],
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080],
    'silenced' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis', 'queue' => ['default'], 'balance' => 'auto',
            'autoScalingStrategy' => 'time', 'maxProcesses' => 4, 'maxTime' => 3600,
            'maxJobs' => 1000, 'memory' => 128, 'tries' => 3, 'timeout' => 60, 'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => ['supervisor-1' => ['maxProcesses' => 4, 'balanceMaxShift' => 1, 'balanceCooldown' => 3]],
        'local' => ['supervisor-1' => ['maxProcesses' => 1]],
    ],
];
