<?php

declare(strict_types=1);

namespace App\Http;

use App\User;
use Crab\Contracts\Http\Handler;
use Crab\Contracts\Http\Middleware;
use Crab\Contracts\Http\Request;
use Crab\Http\HttpException;
use Psr\Http\Message\ResponseInterface;

/**
 * Lets a request through when its user is an administrator. It goes
 * inside TokenAuth, which is what puts the user on the request.
 */
final readonly class AdminsOnly implements Middleware
{
    public function handle(Request $request, Handler $next): ResponseInterface
    {
        if ($request->user(User::class)?->isAdmin() !== true) {
            throw new HttpException(403, 'This is for administrators.');
        }
        return $next->handle($request);
    }
}
