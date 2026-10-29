<?php

declare(strict_types=1);

namespace Tests;

use Crab\Actors\Actors;
use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;
use Psr\Http\Message\RequestInterface;

/**
 * Each customer has an assistant of their own, an actor that remembers
 * the conversation, with a FakeClient standing in for the model.
 */
final class AssistantChatTest extends ShopTestCase
{
    /** @var list<string> */
    private array $prompts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $prompts = &$this->prompts;
        $this->app->container->instance(Client::class, new FakeClient([
            'POST */model/chat' => static function (RequestInterface $request) use (&$prompts) {
                $prompts[] = json_decode((string) $request->getBody(), true)['prompt'];
                return FakeClient::response(200, "data: {\"delta\":\"Here \"}\n\ndata: {\"delta\":\"you go.\"}\n\ndata: [DONE]\n\n", [
                    'Content-Type' => 'text/event-stream',
                ]);
            },
        ]));
    }

    protected function tearDown(): void
    {
        // Actors outlive their test, waiting for their idle time; end them so that one test's memory does not reach the next.
        $this->app->container->get(Actors::class)->stop();
        parent::tearDown();
    }

    public function testTheAssistantRemembersWhatItShowedAndStaysOnTheShelf(): void
    {
        $ada = $this->api->actingAs($this->user('ada@example.com'));

        $first = $ada->post('/assistant/chat', ['question' => 'a mug'])->assertStatus(200)->json();
        self::assertSame(['answer' => 'Here you go.', 'products' => [1], 'turn' => 1], $first);

        // Nothing named: the kitchen shelf, without the mug already shown.
        $second = $ada->post('/assistant/chat', ['question' => 'another one?'])->assertStatus(200)->json();
        self::assertSame(['answer' => 'Here you go.', 'products' => [2], 'turn' => 2], $second);
        self::assertStringContainsString('Earlier: a mug / Here you go.', $this->prompts[1]);
    }

    public function testEachCustomerHasAnAssistantOfTheirOwn(): void
    {
        $this->api->actingAs($this->user('ada@example.com'))->post('/assistant/chat', ['question' => 'a mug'])->assertStatus(200);

        $grace = $this->api->actingAs($this->user('grace@example.com'))->post('/assistant/chat', ['question' => 'a mug']);

        self::assertSame(['answer' => 'Here you go.', 'products' => [1], 'turn' => 1], $grace->assertStatus(200)->json());
    }

    public function testItIsForCustomersWhoAreLoggedIn(): void
    {
        $this->api->post('/assistant/chat', ['question' => 'a mug'])->assertStatus(401);
    }
}
