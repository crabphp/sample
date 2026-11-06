<?php

declare(strict_types=1);

namespace Tests;

/**
 * Checks the public catalogue and the changes only administrators may make.
 */
final class CatalogueTest extends ShopTestCase
{
    public function testTheCatalogueIsPagedSearchedAndCounted(): void
    {
        $page = $this->api->get('/api/products', ['perPage' => 2, 'page' => 2])->assertStatus(200);
        self::assertSame([2, 2, 6, true], [
            count($page->json('data')),
            $page->json('current_page'),
            $page->json('total'),
            $page->json('has_more'),
        ]);
        self::assertSame(['Grid Notebook', 'Linen Cushion'], array_column($page->json('data'), 'name'));
        self::assertSame(
            ['Stoneware Mug'],
            array_column($this->api->get('/api/products', ['q' => 'mug'])->assertStatus(200)->json('data'), 'name'),
        );
        self::assertCount(2, $this->api->get('/api/products', ['category' => 'desk'])->assertStatus(200)->json('data'));

        $categories = array_column(
            $this->api->get('/api/categories')->assertStatus(200)->json(),
            'products',
            'category',
        );
        self::assertSame(['desk' => 2, 'home' => 2, 'kitchen' => 2], $categories);
        $inStock = array_column($this->api->get('/api/categories')->json(), 'in_stock', 'category');
        self::assertSame(['desk' => 2, 'home' => 1, 'kitchen' => 2], $inStock);

        $this->api->get('/api/products/99999')->assertStatus(404);
        // A page size past the limit is refused before the action runs, with the field named where it was: the query string.
        $refused = $this->api->get('/api/products', ['perPage' => 500])->assertStatus(422);
        self::assertArrayHasKey('perPage', $refused->json('error.query'));
    }

    public function testTheShopSaysItIsReadyAndEveryServiceItNamesCanBeMade(): void
    {
        self::assertSame(['status' => 'ready'], $this->api->get('/api/ready')->assertStatus(200)->json());
        self::assertSame([], $this->app->unmade());
    }

    public function testTheCatalogueIsFilteredByPriceAndRefusesFiltersItDoesNotHave(): void
    {
        $prices = array_column($this->api->get('/api/products', ['perPage' => 50])->json('data'), 'price');
        sort($prices);
        $cheap = $this->api
            ->get('/api/products', ['maxPrice' => $prices[1], 'perPage' => 50])
            ->assertStatus(200)
            ->json('data');
        $cheapPrices = array_column($cheap, 'price');
        sort($cheapPrices);
        self::assertSame(array_slice($prices, 0, count($cheapPrices)), $cheapPrices);
        self::assertGreaterThanOrEqual(2, count($cheapPrices));
        self::assertLessThan(count($prices), count($cheapPrices));

        // The two ends of the range are checked together, once each is a valid price.
        self::assertSame(
            ['maxPrice' => ['must be at least the minimum price']],
            $this->api
                ->get('/api/products', ['minPrice' => 5000, 'maxPrice' => 1000])
                ->assertStatus(422)
                ->json('error.query'),
        );
        // A colour is read from the product's JSON details; one that has none, or a null colour, is not any colour.
        self::assertSame(
            ['Cotton Blanket', 'Stoneware Mug'],
            array_column(
                $this->api->get('/api/products', ['colour' => 'blue'])->assertStatus(200)->json('data'),
                'name',
            ),
        );
        self::assertSame([], $this->api->get('/api/products', ['colour' => 'null'])->assertStatus(200)->json('data'));

        // A filter the catalogue does not have is refused, not quietly ignored.
        self::assertSame(
            ['unknown' => ['is not expected']],
            $this->api->get('/api/products', ['unknown' => 'price'])->assertStatus(422)->json('error.query'),
        );
    }

    public function testTheColourFilterKeepsJsonNullApartFromTheTextNull(): void
    {
        $this->db->table('products')->where('id', 5)->update(['details' => '{"colour":"null"}']);
        $products = $this->api->get('/api/products', ['colour' => 'null'])->assertStatus(200)->json('data');

        self::assertSame([5], array_column($products, 'id'));
    }

    public function testTheCatalogueIsChangedOnlyByAdministrators(): void
    {
        $product = [
            'sku' => 'DSK-900',
            'name' => 'Standing Desk',
            'category' => 'desk',
            'price' => 45_000,
            'stock' => 3,
        ];
        $this->api->post('/api/products', $product)->assertStatus(401);
        $this->api->actingAs($this->user('ada@example.com'))->post('/api/products', $product)->assertStatus(403);

        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $before = array_column(
            $this->api->get('/api/categories')->assertStatus(200)->json(),
            'products',
            'category',
        )['desk'];
        $response = $admin->post('/api/products', $product)->assertStatus(201);
        $made = $response->json();
        $response->assertJson('name', 'Standing Desk');
        self::assertSame([$product['sku'], $product['category'], $product['price'], $product['stock']], [
            $made['sku'],
            $made['category'],
            $made['price'],
            $made['stock'],
        ]);
        $response->assertHeader('Location', "/api/products/{$made['id']}");
        $admin->post('/api/products', $product)->assertStatus(409);
        $admin->post('/api/products', ['sku' => 'bad sku'] + $product)->assertStatus(422);
        // The remembered counts were forgotten when the catalogue changed.
        self::assertSame(
            $before + 1,
            array_column($this->api->get('/api/categories')->assertStatus(200)->json(), 'products', 'category')['desk'],
        );

        $admin->put("/api/products/{$made['id']}/stock", ['stock' => 40])->assertStatus(200)->assertJson('stock', 40);
        $admin->put('/api/products/99999/stock', ['stock' => 1])->assertStatus(404);
    }
}
