<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actors\ShoppingAssistant;
use App\User;
use Crab\Contracts\Actors\Actors;
use Crab\Contracts\Actors\NoReply;
use Crab\Http\HttpException;
use Crab\Http\Request;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Length;

/**
 * POST /assistant/chat {"question": "a mug"}, logged in: asks the
 * customer's own ShoppingAssistant actor, which remembers what was said
 * before. Ask "another one?" next.
 */
final readonly class ChatWithAssistant
{
    public function __construct(private Actors $actors)
    {
    }

    public function handle(User $user, #[Fields(['question' => ['string', new Length(1, 200)]])] Data $input, Request $request): array
    {
        try {
            return $this->actors->ask(
                ShoppingAssistant::class,
                "user:{$user->id}",
                ['question' => $input->question],
                timeout: 30,
                cancellation: $request->cancellation(),
            );
        } catch (NoReply) {
            throw new HttpException(503, 'The assistant is not available. Try again in a moment.');
        }
    }
}
