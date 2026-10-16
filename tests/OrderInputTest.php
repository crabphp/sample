<?php

declare(strict_types=1);

namespace Tests;

use App\Http\OrderInput;
use App\Http\OrderLineInput;
use Crab\Validation\ValidationException;
use Crab\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderInputTest extends TestCase
{
    public function testOrderLinesKeepTheQuantityDefaultAndAcceptNull(): void
    {
        $order = (new Validator())->validate(OrderInput::class, ['lines' => [
            ['productId' => 1],
            ['productId' => 2, 'quantity' => null],
            ['productId' => 3, 'quantity' => 4],
        ]]);

        self::assertContainsOnlyInstancesOf(OrderLineInput::class, $order->lines);
        self::assertSame([1, 2, 3], array_column($order->lines, 'productId'));
        self::assertSame([1, null, 4], array_column($order->lines, 'quantity'));
    }

    public function testAnOrderAcceptsTheMaximumNumberOfLinesAndQuantity(): void
    {
        $order = (new Validator())->validate(OrderInput::class, [
            'lines' => array_fill(0, 20, ['productId' => 1, 'quantity' => 20]),
        ]);

        self::assertCount(20, $order->lines);
        self::assertSame(20, $order->lines[19]->quantity);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidOrders')]
    public function testInvalidOrderDataNamesTheField(array $data, string $field): void
    {
        try {
            (new Validator())->validate(OrderInput::class, $data);
            self::fail('Invalid order data was accepted.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey($field, $error->errors);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidOrders(): iterable
    {
        yield 'missing lines' => [[], 'lines'];
        yield 'empty lines' => [['lines' => []], 'lines'];
        yield 'null lines' => [['lines' => null], 'lines'];
        yield 'too many lines' => [['lines' => array_fill(0, 21, ['productId' => 1])], 'lines'];
        yield 'missing product' => [['lines' => [['quantity' => 1]]], 'lines.0.productId'];
        yield 'text product' => [['lines' => [['productId' => '1']]], 'lines.0.productId'];
        yield 'zero quantity' => [['lines' => [['productId' => 1, 'quantity' => 0]]], 'lines.0.quantity'];
        yield 'too many items' => [['lines' => [['productId' => 1, 'quantity' => 21]]], 'lines.0.quantity'];
        yield 'text quantity' => [['lines' => [['productId' => 1, 'quantity' => '2']]], 'lines.0.quantity'];
    }
}
