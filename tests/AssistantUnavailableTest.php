<?php

declare(strict_types=1);

namespace Tests;

use Crab\Actors\Actors;
use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;
use Crab\HttpClient\NetworkException;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;

final class AssistantUnavailableTest extends ShopTestCase
{
    protected function tearDown(): void
    {
        $this->app->container->get(Actors::class)->stop();
        parent::tearDown();
    }

    #[DataProvider('failures')]
    public function testModelFailuresLogOneActionableWarning(string $path, int $status, ?int $modelStatus): void
    {
        $http = new FakeClient([
            '*' => static fn(RequestInterface $request) => $modelStatus ?? new NetworkException(
                'Connection refused.',
                $request,
            ),
        ]);
        $this->app->container->instance(Client::class, $http);
        $api = $this->api->actingAs($this->user('ada@example.com'));
        $isApi = str_starts_with($path, '/api/');
        $response = $isApi
            ? $api->post($path, ['question' => 'mug'])
            : new Browser($api)->post($path, ['question' => 'mug']);

        $response->assertStatus($status);
        self::assertStringContainsString(
            $isApi
                ? 'The assistant is not available. Try again in a moment.'
                : 'We couldn’t complete that request. Please try again in a moment.',
            $response->body(),
        );
        self::assertStringNotContainsString('MODEL_URL', $response->body());
        self::assertSame(0, $this->db->table('assistant_replies')->count());
        self::assertCount(1, $http->requests);

        $log = trim(file_get_contents($this->directory . '/app.log'));
        self::assertCount(1, explode("\n", $log), 'A model failure should log one warning without a duplicate error.');
        self::assertStringContainsString(' WARNING The assistant model at ' . $http->requests[0]->getUri(), $log);
        self::assertStringContainsString($modelStatus === null ? 'Connection refused.' : (string) $modelStatus, $log);
        self::assertStringContainsString('Set MODEL_URL to the model base URL', $log);
        self::assertStringContainsString('set APP_URL to the address the app is served on', $log);
    }

    public static function failures(): iterable
    {
        foreach ([
            '/api/assistant' => 502,
            '/assistant/stream' => 502,
            '/assistant' => 502,
            '/api/assistant/chat' => 503,
            '/assistant/chat' => 503,
        ] as $path => $status) {
            foreach (['connection' => null, 'wrong server' => 404, 'down' => 503] as $why => $modelStatus) {
                yield $path . ' ' . $why => [$path, $status, $modelStatus];
            }
        }
    }
}
