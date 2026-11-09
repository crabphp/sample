<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;

final class ShopAssistantTest extends ShopTestCase
{
    private function fakeModel(): void
    {
        $this->app->container->instance(Client::class, new FakeClient(['POST */api/model/chat' => FakeClient::response(
            200,
            "data: {\"delta\":\"Try <script>bad()</script> a mug.\"}\n\ndata: [DONE]\n\n",
            ['Content-Type' => 'text/event-stream'],
        )]));
    }

    public function testGuestAssistantPostsRedirectsAndRendersWithoutJavascript(): void
    {
        $this->fakeModel();
        $guest = new Browser($this->api);
        $result = $guest->post('/assistant', ['question' => 'a mug <img src=x>'])->assertStatus(303);
        $location = $result->header('Location');
        $html = $guest
            ->get($location)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->body();
        self::assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x&gt;', $html);
        self::assertStringContainsString('Stoneware Mug', $html);
        self::assertStringContainsString('chat-message-user', $html);
        self::assertStringContainsString('chat-message-assistant', $html);
        self::assertStringNotContainsString('<script>bad()', $html);
        new Browser($this->api)->get($location)->assertStatus(404);
        $this->clock->advance('2 days');
        $guest->get($location)->assertStatus(404);
    }

    public function testActorConversationUsesNativeFormsAndRemembersTurns(): void
    {
        $this->fakeModel();
        $browser = new Browser($this->api);
        $browser->cookies['auth'] = $this->app->container->get(TokenStore::class)->issue(
            $this->user('ada@example.com'),
            'browser',
        );
        $first = $browser->post('/assistant/chat', ['question' => 'mug'])->assertStatus(303)->header('Location');
        $firstHtml = $browser->get($first)->assertStatus(200)->body();
        self::assertStringContainsString('Turn 1', $firstHtml);
        self::assertStringContainsString('chat-message-user', $firstHtml);
        $next = $browser
            ->post('/assistant/chat', ['question' => 'another one?'])
            ->assertStatus(303)
            ->header('Location');
        $html = $browser->get($next)->assertStatus(200)->body();
        self::assertStringContainsString('Turn 1', $html);
        self::assertStringContainsString('Turn 2', $html);
        self::assertStringContainsString('Pour-over Kettle', $html);
        new Browser($this->api)
            ->post('/assistant/chat', ['question' => 'mug'])
            ->assertStatus(401);
    }

    public function testProgressiveAnswerStreamKeepsItsEventContract(): void
    {
        $this->fakeModel();
        $events = $this->api
            ->post('/assistant/stream', headers: ['Sec-Fetch-Site' => 'same-origin'], form: ['question' => 'mug'])
            ->assertStatus(200)
            ->events();
        self::assertSame('done', $events[count($events) - 1]['event']);
        self::assertSame([1], $events[count($events) - 1]['data']['products']);
    }
}
