<?php

declare(strict_types=1);

namespace App\Console;

use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\HttpClient\Client;

/**
 * A crowd of pretend customers, for something to watch on the dashboard.
 * It asks the running server what real visitors would, mistakes included.
 */
final readonly class Traffic implements Command
{
    public function __construct(
        private Client $http,
    ) {}

    public function description(): string
    {
        return <<<'TEXT'
            Send the running server the requests of pretend customers
            Options:
              --seconds=30                  How long to keep going, 1 to 86400 seconds
              --rate=200                    Requests a second, 1 to 1000000 at most
              --url=http://127.0.0.1:8080   Where the server is

            Start `php cua dev` first: it runs the queue's workers and the scheduler
            too, so the orders placed here are worked on.
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        $url = rtrim($input->value('url', 'http://127.0.0.1:8080') ?? 'http://127.0.0.1:8080', '/');
        $seconds = $input->value('seconds', '30') ?? '30';
        $rate = $input->value('rate', '200') ?? '200';
        $seconds = filter_var($seconds, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 86_400]]);
        $rate = filter_var($rate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1_000_000]]);
        if ($seconds === false) {
            $output->error('--seconds takes whole seconds from 1 to 86400; use --seconds=30.');
            return 1;
        }
        if ($rate === false) {
            $output->error('--rate takes a whole number from 1 to 1000000; use --rate=200.');
            return 1;
        }
        $http = $this->http->timeout(10);
        $tokens = [];
        foreach (['ada', 'grace', 'alan', 'katherine', 'linus'] as $name) {
            $login = $http->post("{$url}/api/login", ['email' => "{$name}@example.com", 'password' => 'password']);
            if (!$login->ok()) {
                $output->error(
                    "Could not log in as {$name}@example.com at {$url}. Is the server running, and was `shop:seed` run?",
                );
                return 1;
            }
            $tokens[] = $login->json()['token'];
        }
        $products = array_column($http->get("{$url}/api/products", ['perPage' => 50])->json()['data'], 'id');
        $pause = (int) (1_000_000 / $rate);
        $until = microtime(true) + $seconds;
        $sent = $orders = 0;
        while (microtime(true) < $until) {
            $started = microtime(true);
            $auth = ['Authorization' => 'Bearer ' . $tokens[array_rand($tokens)]];
            $product = $products[array_rand($products)];
            match (mt_rand(1, 20)) {
                1, 2, 3, 4, 5 => $http->get("{$url}/api/products", ['page' => mt_rand(1, 3)]),
                6, 7, 8, 9 => $http->get("{$url}/api/products/{$product}"),
                10, 11 => $http->get("{$url}/api/categories"),
                12 => $http->get("{$url}/api/products", ['q' => ['mug', 'wool', 'lamp', 'oak'][mt_rand(0, 3)]]),
                13, 14 => $http->get("{$url}/api/orders", [], $auth),
                15 => $http->get("{$url}/api/me", [], $auth),
                // Things visitors get wrong: a product that is not there, a page that cannot be, no token.
                16 => $http->get("{$url}/api/products/99999"),
                17 => $http->get("{$url}/api/products", ['perPage' => 500]),
                18 => $http->get("{$url}/api/orders"),
                default => $orders += $http->post(
                    "{$url}/api/orders",
                    ['lines' => [['productId' => $product, 'quantity' => mt_rand(1, 2)]]],
                    headers: $auth,
                )->ok()
                    ? 1
                    : 0,
            };
            ++$sent;
            // What is left of this request's share of the second.
            usleep(max(0, $pause - (int) ((microtime(true) - $started) * 1_000_000)));
        }
        $output->line("Sent {$sent} requests and placed {$orders} orders.");
        return 0;
    }
}
