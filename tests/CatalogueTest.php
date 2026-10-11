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
        $page = $this->api->get('/products', ['perPage' => 5, 'page' => 2])->assertStatus(200);
        self::assertSame([5, 2, 36, true], [count($page->json('data')), $page->json('current_page'), $page->json('total'), $page->json('has_more')]);
        self::assertSame(['Stoneware Mug'], array_column($this->api->get('/products', ['q' => 'mug'])->assertStatus(200)->json('data'), 'name'));
        self::assertCount(7, $this->api->get('/products', ['category' => 'desk'])->assertStatus(200)->json('data'));

        $categories = array_column($this->api->get('/categories')->assertStatus(200)->json(), 'products', 'category');
        self::assertSame(['apparel' => 7, 'desk' => 7, 'home' => 7, 'kitchen' => 8, 'outdoors' => 7], $categories);

        $this->api->get('/products/99999')->assertStatus(404);
        // A page size past the limit is refused before the action runs, with the field named.
        $refused = $this->api->get('/products', ['perPage' => 500])->assertStatus(422);
        self::assertArrayHasKey('perPage', $refused->json('error.fields'));
    }

    public function testTheCatalogueIsChangedOnlyByAdministrators(): void
    {
        $product = ['sku' => 'DSK-900', 'name' => 'Standing Desk', 'category' => 'desk', 'price' => 45000, 'stock' => 3];
        $this->api->post('/products', $product)->assertStatus(401);
        $this->api->actingAs($this->user('ada@example.com'))->post('/products', $product)->assertStatus(403);

        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $before = array_column($this->api->get('/categories')->assertStatus(200)->json(), 'products', 'category')['desk'];
        $response = $admin->post('/products', $product)->assertStatus(201);
        $made = $response->json();
        $response->assertJson('name', 'Standing Desk');
        self::assertSame([$product['sku'], $product['category'], $product['price'], $product['stock']], [$made['sku'], $made['category'], $made['price'], $made['stock']]);
        $response->assertHeader('Location', "/products/{$made['id']}");
        $admin->post('/products', $product)->assertStatus(409);
        $admin->post('/products', ['sku' => 'bad sku'] + $product)->assertStatus(422);
        // The remembered counts were forgotten when the catalogue changed.
        self::assertSame($before + 1, array_column($this->api->get('/categories')->assertStatus(200)->json(), 'products', 'category')['desk']);

        $admin->put("/products/{$made['id']}/stock", ['stock' => 40])->assertStatus(200)->assertJson('stock', 40);
        $admin->put('/products/99999/stock', ['stock' => 1])->assertStatus(404);
    }
}
