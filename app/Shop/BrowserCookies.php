<?php

declare(strict_types=1);

namespace App\Shop;

use Crab\Http\Cookie;
use Psr\Http\Message\ServerRequestInterface;

final class BrowserCookies
{
    public static function make(string $name, string $value = '', int $age = 0): Cookie
    {
        $secure = str_starts_with((string) config('app.url'), 'https://');
        return new Cookie(($secure ? '__Host-' : '') . $name, $value, $age, secure: $secure);
    }

    public static function read(ServerRequestInterface $request, string $name): string
    {
        $value = $request->getCookieParams()[self::make($name)->name] ?? '';
        return is_string($value) ? $value : '';
    }
}
