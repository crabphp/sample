<?php

declare(strict_types=1);

namespace Tests;

use Crab\Auth\CookieAuth;
use Crab\OpenApi\DocumentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiTest extends TestCase
{
    #[DataProvider('cookiePolicies')]
    public function testDocumentNamesTheCookieIssuedByTheBoundMiddleware(
        string $url,
        string $expected,
        ?CookieAuth $replacement = null,
    ): void {
        $before = getenv('APP_URL');
        putenv('APP_URL=' . $url);
        try {
            $app = require dirname(__DIR__) . '/app.php';
            if ($replacement !== null) {
                $app->container->instance(CookieAuth::class, $replacement);
            }
            $cookie = $app->container->get(CookieAuth::class)->cookie('example-token');
            self::assertSame($expected, $cookie->name);

            $document = $app->container->get(DocumentBuilder::class)->build(strict: true)->document;
            self::assertSame(
                ['type' => 'apiKey', 'in' => 'cookie', 'name' => $cookie->name],
                $document['components']['securitySchemes']['cookie'],
            );
            self::assertSame([['cookie' => []]], $document['paths']['/checkout']['post']['security']);
            self::assertSame([['cookie' => []]], $document['paths']['/admin/products']['post']['security']);
            self::assertArrayHasKey('text/html', $document['paths']['/']['get']['responses']['200']['content']);
            self::assertArrayHasKey('text/html', $document['paths']['/login']['post']['responses']['401']['content']);
            self::assertSame([['bearer' => []]], $document['paths']['/api/orders']['post']['security']);
        } finally {
            putenv($before === false ? 'APP_URL' : 'APP_URL=' . $before);
        }
    }

    public static function cookiePolicies(): iterable
    {
        yield 'HTTP default' => ['http://shop.example.test', 'auth'];
        yield 'HTTPS default' => ['https://shop.example.test', '__Host-auth'];
        yield 'explicit name' => [
            'https://shop.example.test',
            'shop-session',
            new CookieAuth(name: 'shop-session', secure: true),
        ];
        yield 'Secure on HTTP' => ['http://shop.example.test', '__Host-auth', new CookieAuth(secure: true)];
        yield 'non-Secure on HTTPS' => ['https://shop.example.test', 'auth', new CookieAuth(secure: false)];
    }
}
