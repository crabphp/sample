<?php

declare(strict_types=1);

namespace Tests;

/**
 * Checks password limits, registration and access with real login tokens.
 */
final class AccountTest extends ShopTestCase
{
    public function testAPasswordThatBcryptCannotHashIsRejectedBeforeCreatingAnAccount(): void
    {
        $this->api
            ->post('/api/register', [
                'email' => 'null@example.com',
                'name' => 'Null Byte',
                'password' => "correct\0horse",
            ])
            ->assertStatus(422);
        self::assertFalse($this->db->table('users')->where('email', 'null@example.com')->exists());
    }

    public function testGuessingAPasswordIsSlowedDown(): void
    {
        $statuses = [];
        for ($try = 0; $try < 11; ++$try) {
            $statuses[] = $this->api->post('/api/login', [
                'email' => 'ada@example.com',
                'password' => 'guess ' . $try,
            ])->status;
        }
        self::assertSame([...array_fill(0, 10, 401), 429], $statuses);
        // Registering is counted on its own.
        $this->api
            ->post('/api/register', [
                'email' => 'new@example.com',
                'name' => 'New Person',
                'password' => 'correct horse',
            ])
            ->assertStatus(201);
    }

    public function testAnAccountIsMadeLoggedIntoAndKeptToItsOwner(): void
    {
        $made = $this->api
            ->post('/api/register', [
                'email' => 'new@example.com',
                'name' => 'New Person',
                'password' => 'correct horse',
            ])
            ->assertStatus(201);
        $registered = $this->api->withToken($made->json('token'));
        $registered->get('/api/me')->assertStatus(200)->assertJson('email', 'new@example.com');

        $this->api
            ->post('/api/register', ['email' => 'new@example.com', 'name' => 'Again', 'password' => 'correct horse'])
            ->assertStatus(409);
        $this->api
            ->post('/api/register', ['email' => 'not-an-email', 'name' => 'X', 'password' => 'short'])
            ->assertStatus(422);
        $this->api->post('/api/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(401);
        $this->api->get('/api/me')->assertStatus(401);
        $this->api->get('/api/orders')->assertStatus(401);

        // Ada's login token opens her orders, and reveals nothing of anyone else's.
        $ada = $this->api->withToken($this->login('ada@example.com'));
        $hers = $ada->get('/api/orders')->assertStatus(200)->json('data');
        self::assertNotSame([], $hers);
        $ada->get("/api/orders/{$hers[0]['id']}")->assertStatus(200)->assertJson('id', $hers[0]['id']);
        $notHers = $this->db->table('orders')->where('user_id', '<>', 2)->value('id');
        $ada->get("/api/orders/{$notHers}")->assertStatus(404);
        $admin = $this->api->withToken($this->login('admin@example.com'));
        $admin->get("/api/orders/{$notHers}")->assertStatus(200)->assertJson('id', $notHers);
    }

    /**
     * Tests the real login endpoint and returns its token for the token
     * access checks in this class; other areas use actingAs().
     */
    private function login(string $email): string
    {
        return $this->api
            ->post('/api/login', ['email' => $email, 'password' => 'password'])
            ->assertStatus(200)
            ->json('token');
    }
}
