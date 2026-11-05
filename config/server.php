<?php

declare(strict_types=1);

return [
    'address' => env('SERVER_ADDRESS', 'tcp://127.0.0.1:8080'),

    // Worker processes. More than one needs PHP's pcntl and posix extensions.
    'workers' => env('SERVER_WORKERS', 1),

    // What `php cua dev` watches, to restart the server when you save.
    'watch' => ['app', 'app.php', 'config', 'routes', '.env'],

    // The largest request body taken, in bytes, and how many seconds a client has to send one. Raise both for uploads.
    'max_body' => 1024 * 1024,
    'body_timeout' => 30,

    // Seconds to finish in-flight requests on stop or reload; connections still open at the limit are cut.
    'drain' => 5.0,

    // Where `php cua start --daemon` writes its process id and everything it prints.
    'pid' => 'storage/crab.pid',
    'log' => 'storage/logs/server.log',
];
