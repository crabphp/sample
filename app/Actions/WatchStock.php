<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;
use Crab\Engine\Async\Async;
use Crab\Engine\EventLoop;
use Crab\Http\HttpException;
use Crab\Http\Request;
use Crab\OpenApi\Attributes\Response;

/**
 * A product's stock as server-sent events, for a page that shows it live:
 * GET /api/products/{id}/stock. Returning a generator streams; each value
 * yielded is one event, sent at once. Between two of them it waits a
 * second, and the worker serves everyone else meanwhile.
 *
 *     curl -N http://127.0.0.1:8080/api/products/1/stock
 */
final readonly class WatchStock
{
    private const CHECKS = 60;

    #[Response(
        200,
        body: ['type' => 'string'],
        description: 'Stock changes as named server-sent events. The stream ends after about a minute.',
        contentType: 'text/event-stream',
    )]
    #[Response(404, description: 'No such product.')]
    public function handle(int $id, Request $request): \Generator
    {
        // Checked before the first event: after it, the status has gone and an error can only end the stream.
        $stock = $this->stock($id) ?? throw new HttpException(404, 'No such product.');

        return (function () use ($id, $stock, $request): \Generator {
            yield 'stock' => ['id' => $id, 'stock' => $stock];
            for ($check = 1; $check < self::CHECKS; ++$check) {
                // Stops waiting, and the stream, when the client has gone.
                Async::sleep(EventLoop::current(), 1.0, $request->cancellation());
                $now = $this->stock($id);
                if ($now !== $stock) {
                    $stock = $now;
                    yield 'stock' => ['id' => $id, 'stock' => $stock];
                }
            }
            yield 'bye' => ['reconnect' => true];
        })();
    }

    private function stock(int $id): ?int
    {
        return Product::query()->where('id', $id)->value('stock');
    }
}
