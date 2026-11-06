<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

final class ShopAssets
{
    private const TYPES = [
        'shop.css' => 'text/css; charset=utf-8',
        'shop.js' => 'text/javascript; charset=utf-8',
        'geist-sans.woff2' => 'font/woff2',
        'geist-mono.woff2' => 'font/woff2',
    ];

    #[ApiResponse(200, body: ['type' => 'string'])]
    public function handle(string $file): Response
    {
        $type = self::TYPES[$file] ?? throw new HttpException(404, 'No such asset.');
        return new Response(
            200,
            [
                'Content-Type' => $type,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Content-Type-Options' => 'nosniff',
            ],
            file_get_contents(dirname(__DIR__, 2) . '/resources/assets/' . $file),
        );
    }

    #[ApiResponse(200, body: ['type' => 'object'])]
    public function openapi(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=60'],
            file_get_contents(dirname(__DIR__, 2) . '/openapi.json'),
        );
    }
}
