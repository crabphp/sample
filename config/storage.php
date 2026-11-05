<?php

declare(strict_types=1);

// The report job uses the same disk contract for a local folder, a test's memory or an S3 bucket.
$driver = env('REPORTS_DRIVER', 'local');
$reports = match ($driver) {
    's3' => [
        'driver' => 's3',
        'key' => env('S3_KEY'),
        'secret' => env('S3_SECRET'),
        'bucket' => env('S3_BUCKET'),
        'region' => env('S3_REGION', 'us-east-1'),
        'endpoint' => env('S3_ENDPOINT'),
        'prefix' => 'reports',
    ],
    'memory' => ['driver' => 'memory'],
    default => ['driver' => $driver, 'root' => env('REPORTS_PATH', storage_path('reports'))],
};

return ['disks' => ['reports' => $reports]];
