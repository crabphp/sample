<?php

declare(strict_types=1);

namespace Tests;

use App\Actors\ShoppingAssistant;
use Crab\Actors\Actors;
use Crab\Contracts\Actors\Inbox;
use Crab\Contracts\Actors\Message;
use Crab\Contracts\HttpClient\Client;
use Crab\Engine\Async\Cancellation;
use Crab\HttpClient\FakeClient;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

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
            'POST */api/model/chat' => static function (RequestInterface $request) use (&$prompts) {
                $prompts[] = json_decode((string) $request->getBody(), true)['prompt'];
                return FakeClient::response(
                    200,
                    "data: {\"delta\":\"Here \"}\n\ndata: {\"delta\":\"you go.\"}\n\ndata: [DONE]\n\n",
                    [
                        'Content-Type' => 'text/event-stream',
                    ],
                );
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

        $first = $ada->post('/api/assistant/chat', ['question' => 'a mug'])->assertStatus(200)->json();
        self::assertSame(['answer' => 'Here you go.', 'products' => [1], 'turn' => 1], $first);

        // Nothing named: the kitchen shelf, without the mug already shown.
        $second = $ada->post('/api/assistant/chat', ['question' => 'another one?'])->assertStatus(200)->json();
        self::assertSame(['answer' => 'Here you go.', 'products' => [2], 'turn' => 2], $second);
        self::assertStringContainsString('Earlier: a mug / Here you go.', $this->prompts[1]);
    }

    public function testEachCustomerHasAnAssistantOfTheirOwn(): void
    {
        $this->api
            ->actingAs($this->user('ada@example.com'))
            ->post('/api/assistant/chat', ['question' => 'a mug'])
            ->assertStatus(200);

        $grace = $this->api->actingAs($this->user('grace@example.com'))->post('/api/assistant/chat', [
            'question' => 'a mug',
        ]);

        self::assertSame(
            ['answer' => 'Here you go.', 'products' => [1], 'turn' => 1],
            $grace->assertStatus(200)->json(),
        );
    }

    public function testItIsForCustomersWhoAreLoggedIn(): void
    {
        $this->api->post('/api/assistant/chat', ['question' => 'a mug'])->assertStatus(401);
    }

    public function testLongAnswersDoNotMakeTheNextPromptTooLargeForTheModel(): void
    {
        $prompts = [];
        $this->app->container->instance(Client::class, new FakeClient([
            'POST */api/model/chat' => static function (RequestInterface $request) use (&$prompts) {
                $prompt = json_decode((string) $request->getBody(), true)['prompt'];
                $prompts[] = $prompt;
                if (mb_strlen($prompt) > 4000) {
                    return 422; // The real PretendModel's prompt limit.
                }
                return FakeClient::response(
                    200,
                    'data: ' . json_encode(['delta' => str_repeat('A useful answer. ', 100)]) . "\n\ndata: [DONE]\n\n",
                );
            },
        ]));
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        for ($turn = 1; $turn <= 8; ++$turn) {
            $ada
                ->post('/api/assistant/chat', ['question' => "question {$turn}"])
                ->assertStatus(200)
                ->assertJson('turn', $turn);
        }
        self::assertStringNotContainsString('question 1 /', $prompts[7]);
        self::assertStringContainsString('question 7 /', $prompts[7]);
    }

    public function testAFailedModelRequestDoesNotAdvanceTheConversationOrHideProducts(): void
    {
        $calls = 0;
        $this->app->container->instance(Client::class, new FakeClient([
            '*' => static function () use (&$calls) {
                if (++$calls === 2) {
                    return 503;
                }
                return FakeClient::response(200, "data: {\"delta\":\"Here you go.\"}\n\ndata: [DONE]\n\n");
            },
        ]));
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $ada->post('/api/assistant/chat', ['question' => 'mug'])->assertStatus(200)->assertJson('turn', 1);
        $ada->post('/api/assistant/chat', ['question' => 'kettle'])->assertStatus(503);
        $next = $ada->post('/api/assistant/chat', ['question' => 'another one?'])->assertStatus(200);
        self::assertSame(['answer' => 'Here you go.', 'products' => [2], 'turn' => 2], $next->json());
    }

    public function testAnActiveConversationDoesNotRetainEveryAnswer(): void
    {
        $http = new FakeClient(['*' => FakeClient::response(
            200,
            'data: ' . json_encode(['delta' => str_repeat('x', 16_000)]) . "\n\ndata: [DONE]\n\n",
        )]);
        $inbox = $this->createStub(Inbox::class);
        $inbox->method('cancellation')->willReturn(new Cancellation());
        $warm = $growth = $lastTurn = 0;
        $inbox
            ->method('getIterator')
            ->willReturnCallback(static function () use ($http, &$warm, &$growth, &$lastTurn): \Generator {
                for ($turn = 1; $turn <= 256; ++$turn) {
                    yield new Message(['question' => 'another one?'], static function (bool $ok, mixed $reply) use (
                        &$lastTurn,
                    ): void {
                        $lastTurn = $reply['turn'];
                    });
                    // The test client records requests; keep that out of the actor's memory measurement.
                    $http->requests = [];
                    if ($turn === 32) {
                        $warm = memory_get_usage();
                    }
                }
                $growth = memory_get_usage() - $warm;
            });

        new ShoppingAssistant($this->db, $http, $this->app->container->get(LoggerInterface::class))->handle(
            $inbox,
            'test-conversation',
        );

        self::assertSame(256, $lastTurn);
        self::assertLessThan(1024 * 1024, $growth, 'An active actor retained more than a megabyte of old answers.');
    }
}
