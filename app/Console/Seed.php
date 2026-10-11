<?php

declare(strict_types=1);

namespace App\Console;

use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Connection;

final readonly class Seed implements Command
{
    private const PRODUCTS = [
        'kitchen' => [['Stoneware Mug', 1400], ['Pour-Over Kettle', 6200], ['Cast Iron Skillet', 4800], ['Linen Tea Towels', 1900], ['Walnut Cutting Board', 3900], ['French Press', 3400], ['Ceramic Salt Cellar', 1600], ['Enamel Dutch Oven', 8900]],
        'desk' => [['Brass Pen', 2800], ['Dot Grid Notebook', 1200], ['Felt Desk Mat', 3300], ['Oak Monitor Stand', 7400], ['Cable Clips', 900], ['Task Lamp', 9600], ['Leather Mouse Pad', 2600]],
        'outdoors' => [['Canvas Daypack', 8800], ['Enamel Camp Cup', 1100], ['Wool Blanket', 12900], ['Pocket Knife', 4500], ['Steel Water Bottle', 2900], ['Headlamp', 3700], ['Hammock', 6400]],
        'home' => [['Soy Candle', 2200], ['Linen Throw Pillow', 3800], ['Terracotta Planter', 2700], ['Wall Clock', 5200], ['Cotton Bath Towel', 2400], ['Woven Basket', 3100], ['Incense Holder', 1500]],
        'apparel' => [['Merino Beanie', 3200], ['Canvas Apron', 4100], ['Wool Socks', 1800], ['Chore Jacket', 14500], ['Cotton Tote', 1300], ['Leather Belt', 5600], ['Rain Shell', 17800]],
    ];

    private const PEOPLE = ['Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Katherine Johnson', 'Linus Torvalds', 'Margaret Hamilton', 'Dennis Ritchie', 'Barbara Liskov', 'Ken Thompson', 'Radia Perlman', 'Tim Berners-Lee'];

    public function __construct(private Database $db, private PasswordHasher $passwords, private Cache $cache)
    {
    }

    public function description(): string
    {
        return <<<'TEXT'
            Fill the shop with products, customers and two weeks of orders
            Options:
              --fresh   Empty the shop's tables first
              --products=36 --users=12 --orders=200   Make sized data in batches

            Everyone's password is "password". admin@example.com is an administrator;
            the others, such as ada@example.com, are customers.
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        $sized = $input->option('products') !== null || $input->option('users') !== null || $input->option('orders') !== null;
        $counts = [];
        if ($sized) {
            foreach (['products' => 36, 'users' => 12, 'orders' => 200] as $name => $default) {
                $value = $input->option($name, (string) $default);
                $count = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
                if ($count === false || $count < ($name === 'users' ? 2 : 1)) {
                    $output->error("Give --{$name}= a positive integer" . ($name === 'users' ? ' of at least 2 (an admin and a customer).' : '.'));
                    return 1;
                }
                $counts[$name] = $count;
            }
        }
        $filled = $this->db->table('products')->exists() || $this->db->table('users')->exists() || $this->db->table('orders')->exists();
        if ($filled && !$input->flag('fresh')) {
            $output->line('The shop already has data. Run it with --fresh to start again.');
            return 0;
        }
        // Whatever is there, a customer who registered before any product was added included.
        foreach (['order_items', 'orders', 'products', 'users'] as $table) {
            $this->db->table($table)->delete();
        }
        if ($sized) {
            return $this->sized($counts['products'], $counts['users'], $counts['orders'], $output);
        }
        // The same shop every time, so that what one person sees another can reproduce.
        mt_srand(2026);
        $now = time();
        $at = static fn (int $time): string => date('Y-m-d H:i:s', $time);

        $password = $this->passwords->hash('password');
        $users = [['name' => 'Shop Admin', 'email' => 'admin@example.com', 'password' => $password, 'role' => 'admin', 'created_at' => $at($now - 30 * 86400), 'updated_at' => $at($now - 30 * 86400)]];
        foreach (self::PEOPLE as $index => $name) {
            $joined = $at($now - (29 - $index * 2) * 86400);
            $users[] = ['name' => $name, 'email' => strtolower(strtok($name, ' ')) . '@example.com', 'password' => $password, 'role' => 'member', 'created_at' => $joined, 'updated_at' => $joined];
        }
        $this->db->table('users')->insertMany($users);

        $products = [];
        foreach (self::PRODUCTS as $category => $items) {
            foreach ($items as $index => [$name, $price]) {
                // A few are sold out, so that an order for them is refused.
                $stock = mt_rand(1, 12) === 1 ? 0 : mt_rand(8, 120);
                $products[] = ['sku' => strtoupper(substr($category, 0, 3)) . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'name' => $name, 'category' => $category, 'price' => $price, 'stock' => $stock, 'created_at' => $at($now - 30 * 86400), 'updated_at' => $at($now)];
            }
        }
        $this->db->table('products')->insertMany($products);

        $prices = $this->db->table('products')->pluck('price', 'id');
        $customers = $this->db->table('users')->where('role', 'member')->pluck('id');
        $orders = 0;
        for ($day = 14; $day >= 0; --$day) {
            for ($n = mt_rand(6, 16); $n > 0; --$n) {
                $placed = $now - $day * 86400 - mt_rand(0, 86399);
                if ($placed > $now - 3600) {
                    continue;
                }
                $lines = [];
                $total = 0;
                foreach ((array) array_rand($prices, mt_rand(1, 4)) as $productId) {
                    $quantity = mt_rand(1, 3);
                    $lines[] = ['product_id' => $productId, 'quantity' => $quantity, 'price' => $prices[$productId]];
                    $total += $prices[$productId] * $quantity;
                }
                $orderId = $this->db->table('orders')->insert([
                    'user_id' => $customers[array_rand($customers)], 'status' => mt_rand(1, 10) === 1 ? 'cancelled' : 'paid', 'total' => $total,
                    'created_at' => $at($placed), 'updated_at' => $at($placed + 30),
                ]);
                $this->db->table('order_items')->insertMany(array_map(static fn (array $line): array => ['order_id' => $orderId, ...$line], $lines));
                ++$orders;
            }
        }
        $this->cache->delete('categories');
        $output->line('The shop has ' . count($products) . ' products, ' . count($users) . " people and {$orders} orders.");
        $output->line('Log in as ada@example.com or admin@example.com, with the password "password".');
        return 0;
    }

    /**
     * Sized fixtures use explicit ids so orders and their lines can be
     * inserted together without a query for each generated row. The
     * original fixture above is kept unchanged for ordinary shop users.
     */
    private function sized(int $productCount, int $userCount, int $orderCount, Output $output): int
    {
        mt_srand(2026);
        $now = time();
        $created = date('Y-m-d H:i:s', $now - 30 * 86400);
        $updated = date('Y-m-d H:i:s', $now);
        $password = $this->passwords->hash('password');
        $users = [];
        for ($id = 1; $id <= $userCount; ++$id) {
            $name = $id === 1 ? 'Shop Admin' : (self::PEOPLE[$id - 2] ?? "Customer {$id}");
            $email = $id === 1 ? 'admin@example.com' : ($id <= 12 ? strtolower(strtok($name, ' ')) . '@example.com' : "customer{$id}@bench.example.com");
            $users[] = ['id' => $id, 'name' => $name, 'email' => $email, 'password' => $password, 'role' => $id === 1 ? 'admin' : 'member', 'created_at' => $created, 'updated_at' => $created];
            if (count($users) === 100 || $id === $userCount) {
                $this->db->table('users')->insertMany($users);
                $users = [];
            }
        }
        $catalogue = [];
        foreach (self::PRODUCTS as $category => $items) {
            foreach ($items as [$name, $price]) {
                $catalogue[] = [$category, $name, $price];
            }
        }
        $products = [];
        $prices = [];
        for ($id = 1; $id <= $productCount; ++$id) {
            [$category, $name, $price] = $catalogue[($id - 1) % count($catalogue)];
            $prices[$id] = $price;
            $products[] = ['id' => $id, 'sku' => 'BENCH-' . $id, 'name' => $name . ($id > count($catalogue) ? " {$id}" : ''), 'category' => $category, 'price' => $price, 'stock' => 100_000, 'created_at' => $created, 'updated_at' => $updated];
            if (count($products) === 100 || $id === $productCount) {
                $this->db->table('products')->insertMany($products);
                $products = [];
            }
        }
        for ($first = 1; $first <= $orderCount; $first += 100) {
            $orders = [];
            $items = [];
            for ($id = $first; $id <= min($first + 99, $orderCount); ++$id) {
                $total = 0;
                foreach ((array) array_rand($prices, mt_rand(1, min(4, $productCount))) as $productId) {
                    $quantity = mt_rand(1, 3);
                    $items[] = ['order_id' => $id, 'product_id' => $productId, 'quantity' => $quantity, 'price' => $prices[$productId]];
                    $total += $prices[$productId] * $quantity;
                }
                $placed = date('Y-m-d H:i:s', $now - 3600 - ($id % (14 * 86400)));
                $orders[] = ['id' => $id, 'user_id' => 2 + ($id - 1) % ($userCount - 1), 'status' => $id % 10 === 0 ? 'cancelled' : 'paid', 'total' => $total, 'created_at' => $placed, 'updated_at' => $placed];
            }
            $this->db->transaction(static function (Connection $db) use ($orders, $items): void {
                $db->table('orders')->insertMany($orders);
                foreach (array_chunk($items, 100) as $batch) {
                    $db->table('order_items')->insertMany($batch);
                }
            });
        }
        $this->cache->delete('categories');
        $output->line("The shop has {$productCount} products, {$userCount} people and {$orderCount} orders.");
        $output->line('Log in as ada@example.com or admin@example.com, with the password "password".');
        return 0;
    }
}
