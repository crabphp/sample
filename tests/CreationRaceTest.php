<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Clock\Clock;
use Crab\Tide\Tide;

final class CreationRaceTest extends ShopTestCase
{
    public function testConcurrentRegistrationReturnsConflictAndKeepsTheOtherAccount(): void
    {
        $passwords = $this->createStub(PasswordHasher::class);
        $passwords
            ->method('hash')
            ->willReturnCallback(function (): string {
                // Another request committed after the existence check, before this request's insert.
                $this->db
                    ->table('users')
                    ->insert([
                        'email' => 'race@example.com',
                        'name' => 'First',
                        'role' => 'member',
                        'password' => 'first-hash',
                    ]);
                return 'second-hash';
            });
        $this->app->container->instance(PasswordHasher::class, $passwords);

        $this->api
            ->post('/api/register', [
                'email' => 'race@example.com',
                'name' => 'Second',
                'password' => 'valid password',
            ])
            ->assertStatus(409);

        self::assertSame('First', $this->db->table('users')->where('email', 'race@example.com')->value('name'));
        self::assertSame(0, $this->db->table('auth_tokens')->count());
    }

    public function testConcurrentProductsReturnConflictAndKeepTheOtherProduct(): void
    {
        $clock = $this->createStub(Clock::class);
        $clock
            ->method('now')
            ->willReturnCallback(function (): \DateTimeImmutable {
                $this->db
                    ->table('products')
                    ->insert([
                        'sku' => 'RACE-001',
                        'name' => 'First',
                        'category' => 'desk',
                        'price' => 100,
                        'stock' => 1,
                    ]);
                return $this->clock->now();
            });
        // The product's times are read by Tide as it saves, after the check for the SKU and before the insert.
        Tide::use($this->db, $clock);

        $this->api
            ->actingAs($this->user('admin@example.com'))
            ->post('/api/products', [
                'sku' => 'RACE-001',
                'name' => 'Second',
                'category' => 'desk',
                'price' => 200,
            ])
            ->assertStatus(409);

        self::assertSame('First', $this->db->table('products')->where('sku', 'RACE-001')->value('name'));
    }
}
