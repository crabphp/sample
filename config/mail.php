<?php

declare(strict_types=1);

return [
    // Where the pretend mail server writes each message, one file apiece.
    'path' => env('MAIL_PATH', dirname(__DIR__) . '/storage/mail'),
    // How often it refuses one, from 0 to 1: above 0, confirmation jobs are retried and now and then fail for good.
    'fail_rate' => (float) env('MAIL_FAIL_RATE', 0),
];
