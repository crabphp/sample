<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

final class ShopLegacy
{
    #[ApiResponse(301, headers: ['Location'])]
    public function index(ServerRequestInterface $request): Response
    {
        return $this->handle($request);
    }

    #[ApiResponse(301, headers: ['Location'])]
    public function post(ServerRequestInterface $request, string $path): Response
    {
        return $this->handle($request, $path);
    }

    #[ApiResponse(301, headers: ['Location'])]
    public function handle(ServerRequestInterface $request, string $path = ''): Response
    {
        // Re-encode each segment: a legacy URL can never become a protocol-relative redirect.
        $target = $path === '' ? '/' : '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
        $query = $request->getUri()->getQuery();
        if ($path === '' && $query !== '') {
            $target = '/products';
        }
        return Response::redirect($target . ($query === '' ? '' : '?' . $query), 301);
    }
}
