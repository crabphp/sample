<?php

declare(strict_types=1);

namespace Tests;

use App\Http\ProductSearch;
use App\Models\Order;
use App\Models\Product;
use App\OrderStatus;

final class TideQueryTest extends ShopTestCase
{
    public function testPartialModelsOmitUnreadColumnsAndSaveOnlyTheirChanges(): void
    {
        $product = Product::search(new ProductSearch())->select('name', 'price')->find(1);
        self::assertSame(['id' => 1, 'name' => 'Stoneware Mug', 'price' => 1400], $product->jsonSerialize());
        self::assertFalse(new \ReflectionProperty(Product::class, 'details')->isInitialized($product));
        try {
            $product->details;
            self::fail('An unread column must not masquerade as its null default.');
        } catch (\Error $error) {
            self::assertStringContainsString('must not be accessed before initialization', $error->getMessage());
        }
        $product->price = 1500;
        $product->save();
        $whole = Product::find(1);
        self::assertSame(30, $whole->stock);
        self::assertSame('{"colour": "blue", "dishwasherSafe": true}', $whole->details);
        self::assertSame(1500, $whole->price);
        $product->refresh();
        self::assertSame(30, $product->stock);
        $light = Product::query()->except('details')->find(1);
        self::assertArrayNotHasKey('details', $light->jsonSerialize());
        self::assertSame(30, $light->stock);
        self::assertSame(
            ['id', 'sku', 'name', 'category', 'price', 'stock'],
            array_keys($this->api->get('/api/products')->json('data')[0]),
        );
    }

    public function testTypedValuesAndRelationsKeepTheColumnsTheyNeed(): void
    {
        self::assertSame(OrderStatus::Paid, Order::query()->whereHas('lines')->value('status'));
        self::assertSame(
            [OrderStatus::Paid, OrderStatus::Paid],
            Order::query()->whereHas('lines')->orderBy('id')->pluck('status'),
        );
        self::assertInstanceOf(\DateTimeImmutable::class, Order::query()->whereHas('lines')->max('created_at'));
        $order = Order::query()->select('total')->with('customer', 'lines.product')->find(1);
        self::assertSame(2, $order->user_id);
        self::assertSame('Ada', $order->customer->name);
        self::assertSame('Stoneware Mug', $order->lines[0]->product->name);
        self::assertArrayNotHasKey('password', $order->customer->jsonSerialize());
        $page = Product::query()->select('name')->orderBy('price')->orderBy('id')->cursorPaginate(2);
        self::assertSame([800, 1400], array_column($page->items, 'price'));
        $next = Product::query()->select('name')->orderBy('price')->orderBy('id')->cursorPaginate(2, $page->nextCursor);
        self::assertSame([2500, 4200], array_column($next->items, 'price'));
    }
}
