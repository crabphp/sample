<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;
use Psr\Http\Message\RequestInterface;

/**
 * The assistant relays a model's streamed answer as events of its own,
 * with a FakeClient standing in for the model.
 */
final class AssistantTest extends ShopTestCase
{
    public function testTheAssistantRelaysTheModelsAnswerWordByWord(): void
    {
        $prompts = [];
        $http = new FakeClient([
            'POST */model/chat' => static function (RequestInterface $request) use (&$prompts) {
                $prompts[] = json_decode((string) $request->getBody(), true)['prompt'];
                return FakeClient::response(200, "data: {\"delta\":\"Try \"}\n\ndata: {\"delta\":\"the mug.\"}\n\ndata: [DONE]\n\n", [
                    'Content-Type' => 'text/event-stream',
                ]);
            },
        ]);
        $this->app->container->instance(Client::class, $http);

        $response = $this->api->post('/assistant', ['question' => 'a blue mug'])->assertStatus(200);

        self::assertSame('text/event-stream; charset=utf-8', $response->header('Content-Type'));
        self::assertSame([
            ['event' => 'message', 'data' => ['token' => 'Try ']],
            ['event' => 'message', 'data' => ['token' => 'the mug.']],
            ['event' => 'done', 'data' => ['products' => [1]]],
        ], $response->events());
        self::assertStringContainsString("- Stoneware Mug at $14.00\n", $prompts[0]);
    }

    public function testAModelThatIsDownIsA502BeforeAnyEvent(): void
    {
        $this->app->container->instance(Client::class, new FakeClient(['*' => FakeClient::response(503, 'down')]));

        $this->api->post('/assistant', ['question' => 'lamp'])->assertStatus(502);
    }
}
