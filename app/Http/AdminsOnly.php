<?php

declare(strict_types=1);

namespace App\Http;

use App\User;
use Crab\Contracts\Auth\Identity;
use Crab\Http\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lets a request through when its user is an administrator. It goes
 * inside TokenAuth, which is what puts the user on the request.
 */
final readonly class AdminsOnly implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(Identity::ATTRIBUTE);
        if (!$user instanceof User || !$user->isAdmin()) {
            throw new HttpException(403, 'This is for administrators.');
        }
        return $handler->handle($request);
    }
}
