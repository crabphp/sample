<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Crab\Tide\ModelQuery;

final class ReviewTest extends ShopTestCase
{
    public function testProductAndOrderReviewsKeepTheirTypeEvenWhenIdsOverlap(): void
    {
        $this->api->get('/api/products/1/review-highlight')->assertStatus(200);
        $this->api->post('/api/products/1/reviews', ['rating' => 5, 'body' => 'Nice mug'])->assertStatus(401);
        $this->api = $this->api->actingAs($this->user('ada@example.com'));
        $productReview = $this->api
            ->post('/api/products/1/reviews', ['rating' => 4, 'body' => 'Nice mug'])
            ->assertStatus(201)
            ->json();
        $orderReview = $this->api
            ->post('/api/orders/1/reviews', ['rating' => 5, 'body' => 'Fast delivery'])
            ->assertStatus(201)
            ->json();
        self::assertSame('products', $productReview['reviewable_type']);
        self::assertSame('orders', $orderReview['reviewable_type']);
        self::assertArrayNotHasKey('author', $productReview);
        self::assertArrayNotHasKey('reviewable', $productReview);
        self::assertSame(['Nice mug'], array_column($this->api->get('/api/products/1/reviews')->json('data'), 'body'));
        self::assertSame(
            ['Fast delivery'],
            array_column($this->api->get('/api/orders/1/reviews')->json('data'), 'body'),
        );
        self::assertSame('Nice mug', $this->api->get('/api/products/1/review-highlight')->json('body'));
        self::assertSame(
            'Order #1',
            $this->api
                ->get('/api/reviews/' . $orderReview['id'])
                ->assertStatus(200)
                ->json('subject.label'),
        );
        self::assertSame(
            'Stoneware Mug',
            $this->api->get('/api/reviews/' . $productReview['id'])->json('subject.label'),
        );

        $products = Product::query()->with('reviews')->whereHas('reviews')->get();
        self::assertCount(1, $products);
        self::assertSame('Nice mug', $products[0]->reviews[0]->body);
        self::assertSame(5, Product::query()->whereDoesntHave('reviews')->count());
        self::assertSame(
            0,
            Product::query()
                ->whereHas('reviews', static fn(ModelQuery $query): ModelQuery => $query->where('rating', 5))
                ->count(),
        );
        $loaded = Review::query()->with('reviewable')->orderBy('id')->get();
        self::assertInstanceOf(Product::class, $loaded[0]->reviewable);
        self::assertInstanceOf(Order::class, $loaded[1]->reviewable);

        $this->api = $this->api->actingAs($this->user('grace@example.com'));
        $this->api->get('/api/orders/1/reviews')->assertStatus(404);
        $this->api->post('/api/orders/1/reviews', ['rating' => 5, 'body' => 'Not mine'])->assertStatus(404);
        $this->api->get('/api/reviews/' . $orderReview['id'])->assertStatus(404);
        $this->api->delete('/api/reviews/' . $productReview['id'])->assertStatus(404);
        $this->api = $this->api->actingAs($this->user('ada@example.com'));
        $this->api->delete('/api/reviews/' . $productReview['id'])->assertStatus(204);
        self::assertSame(0, $this->api->get('/api/products/1/reviews')->json('total'));
        self::assertSame(1, $this->api->get('/api/orders/1/reviews')->json('total'));
    }

    public function testHighlightUsesRatingThenNewestAndInputsAreValidated(): void
    {
        $this->api = $this->api->actingAs($this->user('ada@example.com'));
        foreach ([
            [4, 'Good'],
            [5, 'Best'],
            [5, 'Best and newest'],
            [2, 'Newest but lower'],
        ] as [$rating, $body]) {
            $this->api->post('/api/products/1/reviews', ['rating' => $rating, 'body' => $body])->assertStatus(201);
        }
        self::assertSame('Best and newest', $this->api->get('/api/products/1/review-highlight')->json('body'));
        foreach ([
            ['rating' => 0, 'body' => 'No'],
            ['rating' => 6, 'body' => 'No'],
            ['rating' => 5, 'body' => ''],
        ] as $input) {
            $this->api->post('/api/products/1/reviews', $input)->assertStatus(422);
        }
        $this->api->get('/api/products/1/reviews', ['page' => 0])->assertStatus(422);
        $this->api->get('/api/products/999/reviews')->assertStatus(404);
        $this->api->post('/api/products/999/reviews', ['rating' => 5, 'body' => 'Missing'])->assertStatus(404);
        self::assertSame(4, Review::query()->count());
    }

    public function testAssociationAndSavedReviewRollBackTogether(): void
    {
        $review = new Review();
        $review->user_id = 2;
        $review->rating = 5;
        $review->body = 'Retry me';
        try {
            $this->db->transaction(static function () use ($review): void {
                $review->associate('reviewable', Product::find(1))->save();
                throw new \RuntimeException('Retry');
            });
        } catch (\RuntimeException $error) {
            self::assertSame('Retry', $error->getMessage());
        }
        self::assertFalse(isset($review->id));
        self::assertSame(0, Review::query()->count());
        $review->associate('reviewable', Order::find(1))->save();
        self::assertSame('orders', Review::find($review->id)->reviewable_type);
    }
}
