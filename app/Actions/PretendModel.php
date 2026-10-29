<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Engine\Async\Async;
use Crab\Engine\EventLoop;
use Crab\Http\Request;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Length;

/**
 * A pretend AI model, as the warehouse is a pretend warehouse: it answers
 * a prompt word by word, a little at a time, in the server-sent events
 * most AI APIs stream, so the assistant has a real stream to relay
 * without an API key. It "reads" the products listed in the prompt.
 *
 *     data: {"delta":"Here "}
 *     data: [DONE]
 */
final readonly class PretendModel
{
    private const PACE = 0.04;

    public function handle(#[Fields(['prompt' => ['string', new Length(1, 4000)]])] Data $input, Request $request): \Generator
    {
        preg_match_all('/^- (.+)$/m', (string) $input->prompt, $products);
        $answer = $products[1] === []
            ? 'I could not find anything like that in the shop. Try another word, such as mug or lamp.'
            : 'Here is what I would look at: ' . implode('; ', $products[1]) . '. Each is in stock today.';

        foreach (preg_split('/(?<= )/', $answer) ?: [] as $word) {
            // As a model thinks between tokens; the worker serves others meanwhile.
            Async::sleep(EventLoop::current(), self::PACE, $request->cancellation());
            yield ['delta' => $word];
        }
        yield '[DONE]';
    }
}
