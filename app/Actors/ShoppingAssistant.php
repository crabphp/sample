<?php

declare(strict_types=1);

namespace App\Actors;

use App\AssistantModel;
use Crab\Contracts\Actors\Inbox;
use Crab\Contracts\Database\Database;
use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\NetworkException;
use Crab\HttpClient\ResponseException;
use Psr\Log\LoggerInterface;

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
        private LoggerInterface $log,
    ) {}

    public function handle(Inbox $inbox, string $key): void
    {
        $turns = []; // the last four questions and a bounded excerpt of each answer
        $turn = 0;
        $shown = []; // the products already suggested, not suggested again
        $shelf = null; // the category the conversation is about

        foreach ($inbox as $message) {
            $question = (string) ($message->data['question'] ?? '');
            $products = $this->find($question, $shown, $shelf);
            $prompt = "Answer the customer's question from these products.\n";
            foreach ($turns as [$asked, $answered]) {
                $prompt .= "Earlier: {$asked} / {$answered}\n";
            }
            $prompt .= "Question: {$question}\nProducts:\n";
            foreach ($products as $product) {
                $prompt .= sprintf("- %s at $%.2f\n", $product['name'], $product['price'] / 100);
            }

            $answer = '';
            $url = AssistantModel::url();
            try {
                $reply = $this->http
                    ->timeout(30)
                    ->request(
                        'POST',
                        $url,
                        headers: AssistantModel::headers(),
                        json: AssistantModel::payload($prompt),
                        cancellation: $inbox->cancellation(),
                        maxBytes: 64 * 1024,
                    )
                    ->throw();
                foreach ($reply->events() as $event) {
                    if (AssistantModel::done($event)) {
                        break;
                    }
                    $answer .= AssistantModel::delta($event);
                }
            } catch (NetworkException|ResponseException $error) {
                AssistantModel::unavailable($this->log, $url, $error->getMessage());
                $message->reply(['unavailable' => true]);
                continue;
            }
            $answer = trim($answer);
            // A failed request must not mark unseen products as shown or change the conversation's shelf.
            $shelf = $products[0]['category'] ?? $shelf;
            $shown = array_slice([
                ...$shown,
                ...array_map(static fn(array $product): int => (int) $product['id'], $products),
            ], -256);
            // Keep the next prompt within the model's 4000-character input and bound an actor that never idles.
            $turns = array_slice([...$turns, [$question, mb_substr($answer, 0, 500)]], -4);

            $message->reply([
                'answer' => $answer,
                'products' => array_map(static fn(array $product): int => (int) $product['id'], $products),
                'turn' => ++$turn,
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
        $query = fn() => $this->db
            ->current()
            ->table('products')
            ->where('stock', '>', 0)
            ->when($shown !== [], static fn($query) => $query->whereNotIn('id', $shown))
            ->orderBy('id')
            ->limit(3)
            ->select('id', 'name', 'category', 'price');

        $named = $words === []
            ? []
            : $query()->where(static function ($query) use ($words): void {
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
