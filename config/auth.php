<?php

declare(strict_types=1);

return [
    'cookie' => ['login' => '/login'],
    // A login token stops working thirty days after it is issued, so a stolen one does not last.
    'token_lifetime' => (int) env('AUTH_TOKEN_LIFETIME', 30 * 86_400),
];
