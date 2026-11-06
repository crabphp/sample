<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;

final class ShopPriceFilterTest extends ShopTestCase
{
    #[DataProvider('dollarRanges')]
    public function testDollarFiltersIncludeExactlyTheRequestedCents(string $min, string $max, array $ids): void
    {
        foreach ([1 => 1200, 2 => 1250, 3 => 1251] as $id => $price) {
            $this->db->table('products')->where('id', $id)->update(['price' => $price]);
        }
        $html = new Browser($this->api)
            ->get('/products', ['minPrice' => $min, 'maxPrice' => $max, 'sort' => 'price-asc'])
            ->assertStatus(200)
            ->body();
        self::assertSame($ids, self::productIds($html));
        self::assertSame($min, Browser::field($html, 'minPrice'));
        self::assertSame($max, Browser::field($html, 'maxPrice'));
    }

    public static function dollarRanges(): iterable
    {
        yield 'whole dollars' => ['12', '12', [1]];
        yield 'one decimal place' => ['12.5', '12.5', [2]];
        yield 'two decimal places' => ['12.50', '12.50', [2]];
        yield 'inclusive range' => ['12', '12.50', [1, 2]];
        yield 'only maximum' => ['', '12.50', [1, 2]];
        yield 'only minimum' => ['89', '', [5]];
        yield 'largest accepted amount' => ['9999999.99', '', []];
    }

    public function testConversionIsExactForSmallAmountsAndZero(): void
    {
        $browser = new Browser($this->api);
        foreach ([1 => 29, 2 => 0, 3 => 1] as $id => $price) {
            $this->db->table('products')->where('id', $id)->update(['price' => $price]);
        }
        foreach (['0.29' => [1], '0' => [2], '0.00' => [2], '0.01' => [3]] as $amount => $ids) {
            $html = $browser
                ->get('/products', ['minPrice' => $amount, 'maxPrice' => $amount])
                ->assertStatus(200)
                ->body();
            self::assertSame($ids, self::productIds($html));
        }
    }

    public function testDollarValuesSurvivePaginationAndUseTheSharedFormDesign(): void
    {
        $browser = new Browser($this->api);
        $html = $browser
            ->get('/products', ['minPrice' => '12.50', 'maxPrice' => '62.00', 'perPage' => 1, 'sort' => 'price-asc'])
            ->assertStatus(200)
            ->body();
        self::assertSame([1], self::productIds($html));
        self::assertStringContainsString('Min price ($)', $html);
        self::assertStringContainsString('Max price ($)', $html);
        self::assertStringNotContainsString('price (¢)', $html);
        foreach (['minPrice' => '12.50', 'maxPrice' => '62.00'] as $field => $amount) {
            self::assertSame($amount, Browser::field($html, $field));
            self::assertMatchesRegularExpression('/<input[^>]*name="' . $field . '"[^>]*step="0\.01"/', $html);
        }
        self::assertSame(1, preg_match('~href="(/products\?[^"]*page=2)"~', $html, $match));
        $next = html_entity_decode($match[1], ENT_QUOTES);
        self::assertStringContainsString('minPrice=12.50&maxPrice=62.00', $next);
        self::assertStringContainsString('sort=price-asc', $next);
        self::assertSame([6], self::productIds($browser->get($next)->assertStatus(200)->body()));
    }

    #[DataProvider('invalidDollarAmounts')]
    public function testInvalidAmountsAreRejectedByFieldBeforeFiltering(mixed $amount): void
    {
        $browser = new Browser($this->api);
        foreach (['minPrice', 'maxPrice'] as $field) {
            $html = $browser
                ->get('/products', [$field => $amount])
                ->assertStatus(422)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->body();
            self::assertStringContainsString('<strong>' . $field . '</strong>', $html);
            self::assertStringContainsString('Check your details.', $html);
        }
    }

    public static function invalidDollarAmounts(): iterable
    {
        foreach ([
            '-1',
            '-0.01',
            '12.345',
            '12.',
            'abc',
            '1e2',
            '+12',
            'NaN',
            'INF',
            '12,50',
            '$12.50',
            '10000000',
            '999999999999999999999999',
            "12\n",
            ['12.50'],
        ] as $amount) {
            yield [$amount];
        }
    }

    public function testTheRangeAndOtherFiltersAreStillValidated(): void
    {
        $browser = new Browser($this->api);
        $html = $browser->get('/products', ['minPrice' => '12.51', 'maxPrice' => '12.50'])->assertStatus(422)->body();
        self::assertStringContainsString('must be at least the minimum price', $html);
        $browser->get('/products', ['minPrice' => '9', 'maxPrice' => '10'])->assertStatus(200);
        foreach ([['unknown' => 'value'], ['sort' => 'random'], ['page' => 0], ['perPage' => 51]] as $query) {
            $browser->get('/products', $query)->assertStatus(422);
        }
    }

    public function testTheJsonApiStillTakesIntegerCents(): void
    {
        $this->api->get('/api/products', ['minPrice' => '12.50'])->assertStatus(422);
        $this->api->get('/api/products', ['maxPrice' => '12.50'])->assertStatus(422);
        $this->api->get('/api/products', ['maxPrice' => 12])->assertStatus(200)->assertJson('data', []);
        $data = $this->api
            ->get('/api/products', ['minPrice' => 1250, 'maxPrice' => 1400])
            ->assertStatus(200)
            ->json('data');
        self::assertSame([1], array_column($data, 'id'));
        self::assertSame([1400], array_column($data, 'price'));
    }

    private static function productIds(string $html): array
    {
        preg_match_all('~href="/products/(\d+)"~', $html, $matches);
        return array_values(array_unique(array_map(intval(...), $matches[1])));
    }
}
