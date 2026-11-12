<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tag;
use Crab\Tide\ModelQuery;

final class TagTest extends ShopTestCase
{
    public function testTagEditsKeepProductsAndOrdersSeparateAndAreIdempotent(): void
    {
        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $gift = $admin->post('/api/tags', ['name' => 'gift'])->assertStatus(201)->json('id');
        $seasonal = $admin->post('/api/tags', ['name' => 'seasonal'])->assertStatus(201)->json('id');
        $admin->post('/api/tags', ['name' => 'gift'])->assertStatus(409);
        $admin->post('/api/tags', ['name' => 'Not a slug'])->assertStatus(422);
        foreach (['products', 'orders'] as $type) {
            $admin->put('/api/' . $type . '/1/tags', ['tagIds' => [$gift], 'operation' => 'attach'])->assertStatus(200);
        }
        $admin->put('/api/products/1/tags', ['tagIds' => [$gift, $gift], 'operation' => 'attach'])->assertStatus(200);
        self::assertSame(2, $this->db->table('taggables')->count());
        $admin->put('/api/products/1/tags', ['tagIds' => [$seasonal]])->assertStatus(200);
        self::assertSame([$seasonal], array_column($this->api->get('/api/products/1/tags')->json(), 'id'));
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        self::assertSame([$gift], array_column($ada->get('/api/orders/1/tags')->json(), 'id'));
        $admin->put('/api/products/1/tags', ['tagIds' => [$seasonal], 'operation' => 'detach'])->assertStatus(200);
        self::assertSame([], $this->api->get('/api/products/1/tags')->json());
        self::assertSame([$gift], array_column($ada->get('/api/orders/1/tags')->json(), 'id'));
        $admin->put('/api/orders/1/tags', ['tagIds' => [$seasonal], 'operation' => 'attach'])->assertStatus(200);
        $admin->put('/api/orders/1/tags', ['tagIds' => [$gift], 'operation' => 'detach'])->assertStatus(200);
        self::assertSame([$seasonal], array_column($ada->get('/api/orders/1/tags')->json(), 'id'));
        $admin->put('/api/orders/1/tags', ['tagIds' => []])->assertStatus(200);
        self::assertSame([], $ada->get('/api/orders/1/tags')->json());
    }

    public function testTypedTotalsKeepWhereHasAndInverseRelationsKeepTheirMorphType(): void
    {
        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $id = $admin->post('/api/tags', ['name' => 'gift'])->json('id');
        foreach ([1, 3] as $product) {
            $admin->put('/api/products/' . $product . '/tags', ['tagIds' => [$id]])->assertStatus(200);
        }
        $admin->put('/api/orders/1/tags', ['tagIds' => [$id]])->assertStatus(200);
        $summary = $admin
            ->get('/api/tags/' . $id)
            ->assertStatus(200)
            ->json();
        self::assertSame(2, $summary['products']);
        self::assertSame(80, $summary['stock']);
        self::assertSame(1100.0, (float) $summary['average_price']);
        self::assertSame(800, $summary['lowest_price']);
        self::assertSame(1400, $summary['highest_price']);
        self::assertSame(1, $summary['orders']);
        self::assertSame(1400, $summary['order_total']);
        self::assertSame('paid', $summary['latest_order_status']);
        self::assertSame('2026-01-01T12:00:00+00:00', $summary['latest_order_at']);
        self::assertSame(['Stoneware Mug', 'Grid Notebook'], $summary['product_names']);
        self::assertSame(['paid'], $summary['order_statuses']);

        $tag = Tag::query()->with('products', 'orders')->find($id);
        self::assertSame([1, 3], array_column($tag->products, 'id'));
        self::assertSame([1], array_column($tag->orders, 'id'));
        self::assertSame(2200, $tag->related('products')->sum('price'));
        self::assertSame(
            [1, 3],
            $tag->related('products')->where('id', 1)->orWhere('id', 3)->orWhere('id', 2)->orderBy('id')->pluck('id'),
        );
        self::assertSame(4, Product::query()->whereDoesntHave('tags')->count());
        self::assertSame(
            1,
            Tag::query()
                ->whereHas('orders', static fn(ModelQuery $query): ModelQuery => $query->where('id', 1))
                ->count(),
        );
        self::assertSame(
            0,
            Tag::query()
                ->whereHas('orders', static fn(ModelQuery $query): ModelQuery => $query->where('id', 3))
                ->count(),
        );
        $tag->detach('products', [1]);
        self::assertSame([$id], Order::find(1)->related('tags')->pluck('id'));
        $tag->sync('products', [3]);
        self::assertSame([3], $tag->related('products')->pluck('id'));
        $tag->attach('products', [1]);
        self::assertSame([1], $tag->related('orders')->pluck('id'));

        $empty = $admin->post('/api/tags', ['name' => 'unused'])->json('id');
        $summary = $admin
            ->get('/api/tags/' . $empty)
            ->assertStatus(200)
            ->json();
        foreach (['products', 'stock', 'orders', 'order_total'] as $key) {
            self::assertSame(0, $summary[$key]);
        }
        foreach (['average_price', 'lowest_price', 'highest_price', 'latest_order_at', 'latest_order_status'] as $key) {
            self::assertNull($summary[$key]);
        }
        self::assertSame([], $summary['product_names']);
        self::assertSame([], $summary['order_statuses']);
    }

    public function testInvalidEditsAreAtomicAndOrderTagsRemainPrivate(): void
    {
        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $id = $admin->post('/api/tags', ['name' => 'gift'])->json('id');
        $admin->put('/api/products/1/tags', ['tagIds' => [$id]])->assertStatus(200);
        foreach ([
            ['tagIds' => [999]],
            ['tagIds' => [$id, 999]],
            ['tagIds' => [true]],
            ['tagIds' => [], 'operation' => 'replace'],
            ['tagIds' => array_fill(0, 51, $id)],
        ] as $input) {
            $admin->put('/api/products/1/tags', $input)->assertStatus(422);
        }
        self::assertSame([$id], array_column($this->api->get('/api/products/1/tags')->json(), 'id'));
        $this->api->put('/api/products/1/tags', ['tagIds' => []])->assertStatus(401);
        $this->api->get('/api/orders/1/tags')->assertStatus(401);
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $ada->put('/api/products/1/tags', ['tagIds' => []])->assertStatus(403);
        $ada->put('/api/orders/1/tags', ['tagIds' => []])->assertStatus(403);
        $ada->get('/api/tags/' . $id)->assertStatus(403);
        $ada->get('/api/orders/2/tags')->assertStatus(404);
        $admin->put('/api/products/999/tags', ['tagIds' => []])->assertStatus(404);
        $this->api->get('/api/products/999/tags')->assertStatus(404);
        $admin->get('/api/tags/999')->assertStatus(404);
    }
}
