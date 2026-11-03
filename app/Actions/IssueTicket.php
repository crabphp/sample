<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\User;
use Crab\OpenApi\Attributes\Response;

/**
 * Documents the framework's ticket endpoint without adding an OpenAPI
 * dependency to the framework's auth package.
 */
final readonly class IssueTicket
{
    public function __construct(private \Crab\Auth\IssueTicket $tickets) {}

    #[Response(200, body: OpenApiSchemas::TICKET)]
    public function handle(User $user): array
    {
        return $this->tickets->handle($user);
    }
}
