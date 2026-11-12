<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Account;
use App\Models\Preferences;

final class AccountLibraryTest extends ShopTestCase
{
    public function testPreferencesHaveTypedJsonAndBelongToOneAccount(): void
    {
        $this->api->get('/api/me/preferences')->assertStatus(401);
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        self::assertSame(
            ['newsletter' => false, 'categories' => [], 'updated_at' => null],
            $ada->get('/api/me/preferences')->assertStatus(200)->json(),
        );
        $saved = $ada
            ->put('/api/me/preferences', ['newsletter' => true, 'categories' => ['desk', 'kitchen', 'desk']])
            ->assertStatus(200)
            ->json();
        self::assertSame(['desk', 'kitchen'], $saved['categories']);
        self::assertTrue($saved['newsletter']);
        self::assertSame('2026-02-01T12:00:00+00:00', $saved['updated_at']);
        self::assertArrayNotHasKey('accountId', $saved);
        self::assertSame($saved, $ada->get('/api/me/preferences')->json());
        self::assertSame(['desk', 'kitchen'], Preferences::query()->where('user_id', 2)->value('categories'));
        self::assertSame([['desk', 'kitchen']], Preferences::query()->pluck('categories'));
        self::assertTrue(Account::query()->with('preferences')->find(2)->preferences->newsletter);
        self::assertNull(Account::query()->with('preferences')->find(3)->preferences);
        $ada->put('/api/me/preferences', ['newsletter' => true, 'categories' => ['unknown']])->assertStatus(422);
        self::assertSame($saved, $ada->get('/api/me/preferences')->json());
        $this->clock->advance('10 seconds');
        $changed = $ada
            ->put('/api/me/preferences', ['newsletter' => false, 'categories' => []])
            ->assertStatus(200)
            ->json();
        self::assertSame('2026-02-01T12:00:10+00:00', $changed['updated_at']);
        self::assertFalse($changed['newsletter']);
        self::assertSame([], $changed['categories']);
        self::assertSame(1, Preferences::query()->count());
        $grace = $this->api->actingAs($this->user('grace@example.com'));
        self::assertNull($grace->get('/api/me/preferences')->json('updated_at'));
    }

    public function testSavedProductsArePrivateIdempotentAndPartiallySelected(): void
    {
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $grace = $this->api->actingAs($this->user('grace@example.com'));
        $this->api->put('/api/me/saved-products/1')->assertStatus(401);
        $ada->put('/api/me/saved-products/1')->assertStatus(204);
        $ada->put('/api/me/saved-products/1')->assertStatus(204);
        $ada->put('/api/me/saved-products/3')->assertStatus(204);
        $grace->put('/api/me/saved-products/1')->assertStatus(204);
        $list = $ada->get('/api/me/saved-products')->assertStatus(200)->json();
        self::assertSame([3, 1], array_column($list['data'], 'id'));
        self::assertArrayNotHasKey('details', $list['data'][0]);
        self::assertArrayNotHasKey('total', $list);
        self::assertArrayNotHasKey('last_page', $list);
        self::assertSame(20, $list['per_page']);
        self::assertFalse($list['has_more']);
        $ada->delete('/api/me/saved-products/1')->assertStatus(204);
        $ada->delete('/api/me/saved-products/1')->assertStatus(204);
        self::assertSame([1], array_column($grace->get('/api/me/saved-products')->json('data'), 'id'));
        self::assertSame([3], array_column($ada->get('/api/me/saved-products')->json('data'), 'id'));
        $ada->put('/api/me/saved-products/999')->assertStatus(404);
        $ada->get('/api/me/saved-products', ['page' => 0])->assertStatus(422);
        self::assertSame(2, $this->db->table('saved_products')->count());
    }
}
