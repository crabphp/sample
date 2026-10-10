<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Restock;
use App\Actions\SettlePayments;
use Crab\App;
use Crab\Console\Output;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Queue\Store;
use Crab\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * The shop from the outside: each test has a database of its own, with
 * the seeded data, and sends requests as a client would.
 */
final class ShopTest extends TestCase
{
    private App $app;
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crab-sample-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/mail', recursive: true);
        // The real environment is read before .env, so a test can have a shop of its own.
        foreach ([
            'DB_DSN' => 'sqlite:' . $this->directory . '/shop.sqlite', 'CACHE_STORE' => 'array', 'QUEUE_STORE' => 'database',
            'LOG_PATH' => $this->directory . '/app.log', 'MAIL_PATH' => $this->directory . '/mail', 'MAIL_FAIL_RATE' => '0',
            'REPORTS_PATH' => $this->directory . '/reports', 'WAREHOUSE_URL' => '', 'CLOCKWERK_TOKEN' => 'test-token',
        ] as $name => $value) {
            putenv("{$name}={$value}");
        }
        $this->app = require dirname(__DIR__) . '/app.php';
        self::assertSame(0, $this->command('migrate'));
        self::assertSame(0, $this->command('shop:seed'));
    }

    protected function tearDown(): void
    {
        foreach ([...(glob($this->directory . '/*/*') ?: []), ...(glob($this->directory . '/*.*') ?: [])] as $file) {
            unlink($file);
        }
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            rmdir($folder);
        }
        rmdir($this->directory);
    }

    public function testTheCatalogueIsPagedSearchedAndCounted(): void
    {
        $page = $this->json('GET', '/products?perPage=5&page=2');
        self::assertSame([5, 2, 36, true], [count($page['data']), $page['current_page'], $page['total'], $page['has_more']]);
        self::assertSame(['Stoneware Mug'], array_column($this->json('GET', '/products?q=mug')['data'], 'name'));
        self::assertCount(7, $this->json('GET', '/products?category=desk')['data']);

        $categories = array_column($this->json('GET', '/categories'), 'products', 'category');
        self::assertSame(['apparel' => 7, 'desk' => 7, 'home' => 7, 'kitchen' => 8, 'outdoors' => 7], $categories);

        self::assertSame(404, $this->send('GET', '/products/99999')->getStatusCode());
        // A page size past the limit is refused before the action runs, with the field named.
        $refused = $this->send('GET', '/products?perPage=500');
        self::assertSame(422, $refused->getStatusCode());
        self::assertArrayHasKey('perPage', json_decode((string) $refused->getBody(), true)['error']['fields']);
    }

    public function testAnAccountIsMadeLoggedIntoAndKeptToItsOwner(): void
    {
        $made = $this->send('POST', '/register', ['email' => 'new@example.com', 'name' => 'New Person', 'password' => 'correct horse']);
        self::assertSame(201, $made->getStatusCode());
        $token = json_decode((string) $made->getBody(), true)['token'];
        self::assertSame('new@example.com', $this->json('GET', '/me', token: $token)['email']);

        self::assertSame(409, $this->send('POST', '/register', ['email' => 'new@example.com', 'name' => 'Again', 'password' => 'correct horse'])->getStatusCode());
        self::assertSame(422, $this->send('POST', '/register', ['email' => 'not-an-email', 'name' => 'X', 'password' => 'short'])->getStatusCode());
        self::assertSame(401, $this->send('POST', '/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->getStatusCode());
        self::assertSame(401, $this->send('GET', '/me')->getStatusCode());
        self::assertSame(401, $this->send('GET', '/orders')->getStatusCode());

        // Ada sees her own orders, and is told nothing of anyone else's.
        $ada = $this->login('ada@example.com');
        $hers = $this->json('GET', '/orders', token: $ada)['data'];
        self::assertNotSame([], $hers);
        self::assertSame($hers[0]['id'], $this->json('GET', "/orders/{$hers[0]['id']}", token: $ada)['id']);
        $notHers = $this->app->container->get(Database::class)->table('orders')->where('user_id', '<>', 2)->value('id');
        self::assertSame(404, $this->send('GET', "/orders/{$notHers}", token: $ada)->getStatusCode());
        self::assertSame($notHers, $this->json('GET', "/orders/{$notHers}", token: $this->login('admin@example.com'))['id']);
    }

    public function testAnOrderTakesStockAndItsWorkIsDoneByTheQueue(): void
    {
        $db = $this->app->container->get(Database::class);
        $db->table('products')->where('id', 1)->update(['stock' => 5]);
        $ada = $this->login('ada@example.com');

        $placed = $this->send('POST', '/orders', ['lines' => [['productId' => 1, 'quantity' => 2], ['productId' => 2], ['productId' => 1, 'quantity' => 1]]], $ada);
        self::assertSame(201, $placed->getStatusCode());
        $order = json_decode((string) $placed->getBody(), true);
        // Three mugs at 14.00 and one kettle at 62.00.
        self::assertSame(['pending', 3 * 1400 + 6200], [$order['status'], $order['total']]);
        self::assertSame("/orders/{$order['id']}", $placed->getHeaderLine('Location'));
        self::assertSame(2, (int) $db->table('products')->where('id', 1)->value('stock'));

        // More than there is: refused, and nothing of the order is kept.
        $orders = $db->table('orders')->count();
        $db->table('products')->where('id', 2)->update(['stock' => 9]);
        self::assertSame(409, $this->send('POST', '/orders', ['lines' => [['productId' => 2], ['productId' => 1, 'quantity' => 3]]], $ada)->getStatusCode());
        // Neither the order nor what its other lines had already taken: the kettle is back on the shelf.
        self::assertSame([$orders, 2, 9], [$db->table('orders')->count(), (int) $db->table('products')->where('id', 1)->value('stock'), (int) $db->table('products')->where('id', 2)->value('stock')]);
        self::assertSame(422, $this->send('POST', '/orders', ['lines' => []], $ada)->getStatusCode());
        self::assertSame(422, $this->send('POST', '/orders', ['lines' => [['productId' => 1, 'quantity' => 99]]], $ada)->getStatusCode());

        // The event's listener queued a confirmation on "mail" and the warehouse on "default".
        $queue = $this->app->container->get(Store::class);
        self::assertSame([1, 1], [$queue->size('mail'), $queue->size('default')]);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame([0, 0], [$queue->size('mail'), $queue->size('default')]);
        $mail = glob($this->directory . '/mail/*.txt') ?: [];
        self::assertCount(1, $mail);
        $message = (string) file_get_contents($mail[0]);
        self::assertStringContainsString('To: ada@example.com', $message);
        self::assertStringContainsString("Subject: Your order #{$order['id']}", $message);
        self::assertStringContainsString('3 x Stoneware Mug  $42.00', $message);
        self::assertStringContainsString('Total: $104.00', $message);
    }

    public function testOrdersArePaidCancelledAndTheShelvesRefilledByTheClock(): void
    {
        $db = $this->app->container->get(Database::class);
        $db->table('products')->where('id', 1)->update(['stock' => 5]);
        $ada = $this->login('ada@example.com');
        $ids = [];
        for ($i = 0; $i < 12; ++$i) {
            $ids[] = $this->json('POST', '/orders', ['lines' => [['productId' => 3]]], $ada)['id'];
        }
        $old = date('Y-m-d H:i:s', time() - 3600);
        $db->table('orders')->whereIn('id', $ids)->update(['created_at' => $old]);
        $stock = (int) $db->table('products')->where('id', 3)->value('stock');

        // What the scheduler runs every ten seconds: all are paid but the one in ten that never is.
        $this->app->container->get(SettlePayments::class)->handle();
        $unpaid = $db->table('orders')->whereIn('id', $ids)->where('status', 'pending')->pluck('id');
        self::assertSame(array_values(array_filter($ids, static fn (int $id): bool => $id % 10 === 0)), $unpaid);
        self::assertNotSame([], $unpaid);

        // Every minute: those are cancelled, and what they took is back in stock.
        self::assertSame(0, $this->command('orders:cancel-stale'));
        self::assertSame(count($unpaid), $db->table('orders')->whereIn('id', $ids)->where('status', 'cancelled')->count());
        self::assertSame($stock + count($unpaid), (int) $db->table('products')->where('id', 3)->value('stock'));

        $this->app->container->get(Restock::class)->handle();
        self::assertSame(65, (int) $db->table('products')->where('id', 1)->value('stock'));

        self::assertSame(0, $this->command('schedule:list'));
    }

    public function testTheCatalogueIsChangedOnlyByAdministrators(): void
    {
        $product = ['sku' => 'DSK-900', 'name' => 'Standing Desk', 'category' => 'desk', 'price' => 45000, 'stock' => 3];
        self::assertSame(401, $this->send('POST', '/products', $product)->getStatusCode());
        self::assertSame(403, $this->send('POST', '/products', $product, $this->login('ada@example.com'))->getStatusCode());

        $admin = $this->login('admin@example.com');
        $before = array_column($this->json('GET', '/categories'), 'products', 'category')['desk'];
        $made = $this->json('POST', '/products', $product, $admin);
        self::assertSame('Standing Desk', $made['name']);
        self::assertSame(409, $this->send('POST', '/products', $product, $admin)->getStatusCode());
        self::assertSame(422, $this->send('POST', '/products', ['sku' => 'bad sku'] + $product, $admin)->getStatusCode());
        // The remembered counts were forgotten when the catalogue changed.
        self::assertSame($before + 1, array_column($this->json('GET', '/categories'), 'products', 'category')['desk']);

        self::assertSame(40, $this->json('PUT', "/products/{$made['id']}/stock", ['stock' => 40], $admin)['stock']);
        self::assertSame(404, $this->send('PUT', '/products/99999/stock', ['stock' => 1], $admin)->getStatusCode());
    }

    public function testTheDashboardIsThereAndNeedsItsToken(): void
    {
        $this->send('GET', '/products');
        self::assertSame(200, $this->send('GET', '/clockwerk')->getStatusCode());
        self::assertSame(401, $this->send('GET', '/clockwerk/api')->getStatusCode());
        $state = $this->json('GET', '/clockwerk/api?token=test-token');
        self::assertSame('Crab Shop', $state['app']);
        self::assertSame(['mail', 'default'], array_column($state['jobs']['supervisors'], 'name'));
        self::assertSame(['settle-payments', 'command orders:cancel-stale', 'restock', 'daily-report'], array_column($state['tasks']['rows'], 'name'));
    }

    private function login(string $email): string
    {
        return $this->json('POST', '/login', ['email' => $email, 'password' => 'password'])['token'];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function send(string $method, string $path, ?array $body = null, ?string $token = null): \Psr\Http\Message\ResponseInterface
    {
        $headers = ['Accept' => 'application/json'] + ($body === null ? [] : ['Content-Type' => 'application/json']) + ($token === null ? [] : ['Authorization' => "Bearer {$token}"]);
        return $this->app->handle(new Request($method, $path, $headers, $body === null ? '' : json_encode($body)));
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<int|string, mixed>
     */
    private function json(string $method, string $path, ?array $body = null, ?string $token = null): array
    {
        $response = $this->send($method, $path, $body, $token);
        self::assertLessThan(300, $response->getStatusCode(), "{$method} {$path} answered {$response->getStatusCode()}: {$response->getBody()}");
        return json_decode((string) $response->getBody(), true);
    }

    private function command(string ...$words): int
    {
        $stream = fopen('php://memory', 'w+');
        return $this->app->console->run($words, new Output($stream, $stream));
    }
}
