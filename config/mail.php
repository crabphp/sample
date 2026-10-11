<?php

declare(strict_types=1);

return [
    // Where the pretend mail server writes each message, one file apiece.
    'path' => env('MAIL_PATH', dirname(__DIR__) . '/storage/mail'),
    // How often it refuses one, from 0 to 1, so that there are retried and failed jobs to look at. 0 refuses none.
    'fail_rate' => (float) env('MAIL_FAIL_RATE', 0.15),
];
