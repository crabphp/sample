<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\SettlePayments;
use Crab\Testing\FrozenClock;
use Crab\Testing\WebSocketRefused;
use Psr\Clock\ClockInterface;

/**
 * A customer watches only their own order through the real socket API,
 * and committed status changes arrive over the joined channel.
 */
final class LiveOrderTest extends ShopTestCase
{
    public function testTheOwnerReceivesAShipmentMadeOverHttp(): void
    {
        $owner = $this->user('ada@example.com');
        $id = (int) $this->db->table('orders')->where('user_id', $owner->id)->where('status', 'paid')->value('id');
        self::assertGreaterThan(0, $id);
        $socket = $this->api->actingAs($owner)->websocket("/orders/{$id}/live");
        $socket->assertReceived(['type' => 'ready', 'id' => $id, 'status' => 'paid', 'version' => 1]);
        $this->api->actingAs($this->user('admin@example.com'))->put("/orders/{$id}/ship")->assertStatus(200)->assertJson('status', 'shipped');
        $socket->assertReceived(['type' => 'status', 'id' => $id, 'status' => 'shipped', 'version' => 2]);
        self::assertSame(2, (int) $this->db->table('orders')->where('id', $id)->value('version'));
        $this->api->actingAs($owner)->put("/orders/{$id}/ship")->assertStatus(403);
        $this->api->actingAs($this->user('admin@example.com'))->put("/orders/{$id}/ship")->assertStatus(409);
        $socket->assertNothingReceived();
        $this->api->actingAs($this->user('admin@example.com'))->put('/orders/999999/ship')->assertStatus(404);
    }

    public function testAnotherUserCannotWatchTheOrderAndAMissingOrderDoesNotUpgrade(): void
    {
        $owner = $this->user('ada@example.com');
        $id = (int) $this->db->table('orders')->where('user_id', $owner->id)->value('id');
        foreach ([$id, 999999] as $order) {
            try {
                $this->api->actingAs($this->user('grace@example.com'))->websocket("/orders/{$order}/live");
                self::fail('An inaccessible order upgraded.');
            } catch (WebSocketRefused $error) {
                $error->response->assertStatus(404);
            }
        }
    }

    public function testABrowserTicketConnectsAndCannotBeUsedTwice(): void
    {
        $token = $this->api->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertStatus(200)->json('token');
        $id = (int) $this->db->table('orders')->where('user_id', $this->user('ada@example.com')->id)->where('status', 'paid')->value('id');
        $ticket = $this->api->withToken($token)->post('/ws/ticket')->assertStatus(200)->json('ticket');
        $this->api->websocket("/orders/{$id}/live", query: ['ticket' => $ticket])->assertReceived(['type' => 'ready', 'id' => $id, 'status' => 'paid', 'version' => 1]);
        try {
            $this->api->websocket("/orders/{$id}/live", query: ['ticket' => $ticket]);
            self::fail('A used ticket upgraded.');
        } catch (WebSocketRefused $error) {
            $error->response->assertStatus(401);
        }
    }

    public function testTheOwnerReceivesScheduledPaymentAndCancellationUpdates(): void
    {
        $this->app->container->instance(ClockInterface::class, new FrozenClock('2026-01-31 12:00:00'));
        $owner = $this->user('ada@example.com');
        $api = $this->api->actingAs($owner);
        $ids = [];
        for ($i = 0; $i < 11; ++$i) {
            $ids[] = $api->post('/orders', ['lines' => [['productId' => 3]]])->assertStatus(201)->json('id');
        }
        $paid = array_values(array_filter($ids, static fn (int $id): bool => $id % 10 !== 0))[0];
        $cancelled = array_values(array_filter($ids, static fn (int $id): bool => $id % 10 === 0))[0];
        $this->db->table('orders')->whereIn('id', $ids)->update(['created_at' => '2020-01-01 00:00:00']);
        $paymentSocket = $api->websocket("/orders/{$paid}/live");
        $cancelSocket = $api->websocket("/orders/{$cancelled}/live");
        $paymentSocket->assertReceived(['type' => 'ready', 'id' => $paid, 'status' => 'pending', 'version' => 1]);
        $cancelSocket->assertReceived(['type' => 'ready', 'id' => $cancelled, 'status' => 'pending', 'version' => 1]);
        $this->app->container->get(SettlePayments::class)->handle();
        $paymentSocket->assertReceived(['type' => 'status', 'id' => $paid, 'status' => 'paid', 'version' => 2]);
        $paidAt = $this->db->table('orders')->where('id', $paid)->value('updated_at');
        self::assertSame(0, $this->command('orders:cancel-stale'));
        $cancelSocket->assertReceived(['type' => 'status', 'id' => $cancelled, 'status' => 'cancelled', 'version' => 2]);
        self::assertSame(2, (int) $this->db->table('orders')->where('id', $cancelled)->value('version'));
        $this->api->actingAs($this->user('admin@example.com'))->put("/orders/{$paid}/ship")->assertStatus(200);
        $paymentSocket->assertReceived(['type' => 'status', 'id' => $paid, 'status' => 'shipped', 'version' => 3]);
        self::assertSame(3, (int) $this->db->table('orders')->where('id', $paid)->value('version'));
        self::assertSame($paidAt, $this->db->table('orders')->where('id', $paid)->value('updated_at'));
    }
}
