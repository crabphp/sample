<?php

declare(strict_types=1);

namespace App\Actors;

use Crab\Contracts\Actors\Inbox;
use Crab\Contracts\Database\Database;
use Crab\Contracts\HttpClient\Client;

/**
 * One customer's shopping assistant, an actor: one runs for each
 * customer, takes their questions one at a time, and remembers the
 * conversation in its variables while it runs. Ask for "a mug", then
 * "another one?", and it suggests what it has not shown yet, from the
 * same shelf.
 *
 * It ends after a minute without a question ("actors.idle"), and forgets.
 * With the local broadcast carrier it runs in the HTTP worker that asked;
 * with Redis, in the "actors" server, wherever the customer's requests land.
 */
final readonly class ShoppingAssistant
{
    public function __construct(
        private Database $db,
        private Client $http,
    ) {
    }

    public function handle(Inbox $inbox, string $key): void
    {
        $turns = [];       // what was asked and answered, for the model
        $shown = [];       // the products already suggested, not suggested again
        $shelf = null;     // the category the conversation is about

        foreach ($inbox as $message) {
            $question = (string) ($message->data['question'] ?? '');
            $products = $this->find($question, $shown, $shelf);
            $shelf = $products[0]['category'] ?? $shelf;
            $shown = [...$shown, ...array_map(static fn(array $product): int => (int) $product['id'], $products)];

            $prompt = "Answer the customer's question from these products.\n";
            foreach (array_slice($turns, -4) as [$asked, $answered]) {
                $prompt .= "Earlier: {$asked} / {$answered}\n";
            }
            $prompt .= "Question: {$question}\nProducts:\n";
            foreach ($products as $product) {
                $prompt .= sprintf("- %s at $%.2f\n", $product['name'], $product['price'] / 100);
            }

            $answer = '';
            $reply = $this->http->timeout(30)->request('POST', config('shop.model_url') . '/model/chat', json: ['prompt' => $prompt], cancellation: $inbox->cancellation())->throw();
            foreach ($reply->events() as $event) {
                if ($event->data === '[DONE]') {
                    break;
                }
                $answer .= $event->json()['delta'] ?? '';
            }
            $answer = trim($answer);
            $turns[] = [$question, $answer];

            $message->reply([
                'answer' => $answer,
                'products' => array_map(static fn(array $product): int => (int) $product['id'], $products),
                'turn' => count($turns),
            ]);
        }
    }

    /**
     * Up to three products in stock that the question names, or else from
     * the shelf the conversation is on, leaving out those already shown.
     *
     * @param list<int> $shown
     * @return list<array<string, mixed>>
     */
    private function find(string $question, array $shown, ?string $shelf): array
    {
        $words = array_values(array_filter(
            preg_split('/\W+/', strtolower($question), flags: PREG_SPLIT_NO_EMPTY) ?: [],
            static fn(string $word): bool => strlen($word) > 2,
        ));
        $query = fn() => $this->db->table('products')
            ->where('stock', '>', 0)
            ->when($shown !== [], static fn($query) => $query->whereNotIn('id', $shown))
            ->orderBy('id')
            ->limit(3)
            ->select('id', 'name', 'category', 'price');

        $named = $words === [] ? [] : $query()->where(static function ($query) use ($words): void {
            foreach ($words as $word) {
                $query->orWhere('name', 'like', "%{$word}%")->orWhere('category', $word);
            }
        })->get();
        if ($named !== [] || $shelf === null) {
            return $named;
        }
        return $query()->where('category', $shelf)->get();
    }
}
