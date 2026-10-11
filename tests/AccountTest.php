<?php

declare(strict_types=1);

namespace Tests;

/**
 * Checks password limits, registration and access with real login tokens.
 */
final class AccountTest extends ShopTestCase
{
    public function testGuessingAPasswordIsSlowedDown(): void
    {
        $statuses = [];
        for ($try = 0; $try < 11; ++$try) {
            $statuses[] = $this->api->post('/login', ['email' => 'ada@example.com', 'password' => 'guess ' . $try])->status;
        }
        self::assertSame([...array_fill(0, 10, 401), 429], $statuses);
        // Registering is counted on its own.
        $this->api->post('/register', ['email' => 'new@example.com', 'name' => 'New Person', 'password' => 'correct horse'])->assertStatus(201);
    }

    public function testAnAccountIsMadeLoggedIntoAndKeptToItsOwner(): void
    {
        $made = $this->api->post('/register', ['email' => 'new@example.com', 'name' => 'New Person', 'password' => 'correct horse'])->assertStatus(201);
        $registered = $this->api->withToken($made->json('token'));
        $registered->get('/me')->assertStatus(200)->assertJson('email', 'new@example.com');

        $this->api->post('/register', ['email' => 'new@example.com', 'name' => 'Again', 'password' => 'correct horse'])->assertStatus(409);
        $this->api->post('/register', ['email' => 'not-an-email', 'name' => 'X', 'password' => 'short'])->assertStatus(422);
        $this->api->post('/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(401);
        $this->api->get('/me')->assertStatus(401);
        $this->api->get('/orders')->assertStatus(401);

        // Ada's login token opens her orders, and reveals nothing of anyone else's.
        $ada = $this->api->withToken($this->login('ada@example.com'));
        $hers = $ada->get('/orders')->assertStatus(200)->json('data');
        self::assertNotSame([], $hers);
        $ada->get("/orders/{$hers[0]['id']}")->assertStatus(200)->assertJson('id', $hers[0]['id']);
        $notHers = $this->db->table('orders')->where('user_id', '<>', 2)->value('id');
        $ada->get("/orders/{$notHers}")->assertStatus(404);
        $admin = $this->api->withToken($this->login('admin@example.com'));
        $admin->get("/orders/{$notHers}")->assertStatus(200)->assertJson('id', $notHers);
    }

    /**
     * Tests the real login endpoint and returns its token for the token
     * access checks in this class; other areas use actingAs().
     */
    private function login(string $email): string
    {
        return $this->api->post('/login', ['email' => $email, 'password' => 'password'])->assertStatus(200)->json('token');
    }
}
