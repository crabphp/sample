<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\HttpClient\Client;
use Crab\Http\Request;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Length;

/**
 * The shop's assistant: POST /assistant {"question": "a blue mug"}. It
 * finds the products the question is about, asks a model, and relays the
 * model's answer to the client word by word as it arrives: the HTTP
 * client's stream() reads the model's events, and each value this action
 * yields is an event of its own.
 *
 *     curl -N -X POST localhost:8080/assistant -H 'Content-Type: application/json' -d '{"question":"a blue mug"}'
 */
final readonly class AskAssistant
{
    public function __construct(
        private Database $db,
        private Client $http,
    ) {
    }

    public function handle(#[Fields(['question' => ['string', new Length(1, 200)]])] Data $input, Request $request): \Generator
    {
        $words = array_filter(
            preg_split('/\W+/', strtolower((string) $input->question), flags: PREG_SPLIT_NO_EMPTY) ?: [],
            static fn(string $word): bool => strlen($word) > 2,
        );
        $products = $words === [] ? [] : $this->db->table('products')
            ->where(static function ($query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere('name', 'like', "%{$word}%")->orWhere('category', $word);
                }
            })
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->limit(3)
            ->select('id', 'name', 'price')
            ->get();

        $prompt = "Answer the customer's question from these products.\nQuestion: {$input->question}\nProducts:\n";
        foreach ($products as $product) {
            $prompt .= sprintf("- %s at $%.2f\n", $product['name'], $product['price'] / 100);
        }

        // Asked before the first yield, so that a model that is down is a 502, not a stream cut short.
        $reply = $this->http
            ->timeout(60)
            ->stream('POST', config('shop.model_url') . '/model/chat', json: ['prompt' => $prompt], cancellation: $request->cancellation());
        if (!$reply->ok()) {
            throw new \Crab\Http\HttpException(502, 'The assistant is not available. Try again in a moment.');
        }

        return (static function () use ($reply, $products): \Generator {
            foreach ($reply->events() as $event) {
                if ($event->data === '[DONE]') {
                    break;
                }
                yield ['token' => $event->json()['delta'] ?? ''];
            }
            yield 'done' => ['products' => array_map(static fn(array $product): int => (int) $product['id'], $products)];
        })();
    }
}
