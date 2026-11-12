<?php

declare(strict_types=1);

// A visit's session rides in its own cookie, signed with app.key, so nothing is kept on the server:
// the flash message after a form, and the ids of a guest's cart and assistant replies, whose data
// stays in their tables. A guest's cart is kept for 30 days without a visit, as before.
return [
    'driver' => env('SESSION_DRIVER', 'cookie'),
    'lifetime' => 30 * 86_400,
    // Secure where the shop is served over HTTPS; plain HTTP while developing.
    'secure' => str_starts_with((string) (env('APP_URL') ?? ''), 'https://'),
];
