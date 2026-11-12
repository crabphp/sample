<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\NewReview;
use App\Http\OpenApiSchemas;
use App\Models\Account;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\User;
use Crab\Contracts\Database\Page;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Routing\FromQuery;
use Crab\Tide\ModelQuery;
use Crab\Validation\Range;

#[ApiResponse(404, description: 'No such review or subject, or the order belongs to another customer.')]
final readonly class Reviews
{
    #[ApiResponse(200, body: OpenApiSchemas::REVIEW_PAGE)]
    public function product(int $id, #[FromQuery]
        #[Range(min: 1)] int $page = 1): Page
    {
        $product = Product::find($id) ?? throw new HttpException(404, 'No such product.');
        return $product->related('reviews')->orderBy('id', 'desc')->paginate(20, $page);
    }

    #[ApiResponse(200, body: OpenApiSchemas::REVIEW_PAGE)]
    public function order(User $user, int $id, #[FromQuery]
        #[Range(min: 1)] int $page = 1): Page
    {
        $order = $this->ownedOrder($user, $id);
        return $order->related('reviews')->orderBy('id', 'desc')->paginate(20, $page);
    }

    #[ApiResponse(201, body: OpenApiSchemas::REVIEW)]
    public function createProduct(User $user, int $id, NewReview $input): Response
    {
        $product = Product::find($id) ?? throw new HttpException(404, 'No such product.');
        return $this->create($user, $product, $input);
    }

    #[ApiResponse(201, body: OpenApiSchemas::REVIEW)]
    public function createOrder(User $user, int $id, NewReview $input): Response
    {
        return $this->create($user, $this->ownedOrder($user, $id), $input);
    }

    // A single related row, chosen explicitly: highest rating, then newest.
    #[ApiResponse(200, body: ['oneOf' => [OpenApiSchemas::REVIEW, ['type' => 'null']]])]
    public function highlight(int $id): Response
    {
        $product = Product::query()
            ->with('highlight', static fn(ModelQuery $query): ModelQuery => $query->orderBy('rating', 'desc')->orderBy(
                'id',
                'desc',
            ))
            ->find($id) ?? throw new HttpException(404, 'No such product.');
        return Response::json($product->highlight);
    }

    #[ApiResponse(200, body: OpenApiSchemas::REVIEW_SUBJECT)]
    public function show(User $user, int $id): array
    {
        $review = Review::query()->with('reviewable')->find($id) ?? throw new HttpException(404, 'No such review.');
        if ($review->reviewable instanceof Order) {
            $this->ownedOrder($user, $review->reviewable->id);
        }
        // Keep the private order's customer and internal columns off this small subject link.
        $subject = [
            'type' => $review->reviewable::morphName(),
            'id' => $review->reviewable->id,
            'label' => $review->reviewable instanceof Product
                ? $review->reviewable->name
                : 'Order #' . $review->reviewable->id,
        ];
        unset($review->reviewable);
        return ['review' => $review, 'subject' => $subject];
    }

    #[ApiResponse(204)]
    public function delete(User $user, int $id): Response
    {
        $review = Review::find($id) ?? throw new HttpException(404, 'No such review.');
        if ($review->user_id !== (int) $user->id) {
            throw new HttpException(404, 'No such review.');
        }
        $review->delete();
        return new Response(204);
    }

    private function ownedOrder(User $user, int $id): Order
    {
        return Order::query()->where('user_id', $user->id)->find($id) ?? throw new HttpException(404, 'No such order.');
    }

    private function create(User $user, Product|Order $subject, NewReview $input): Response
    {
        $review = new Review();
        $review->rating = $input->rating;
        $review->body = $input->body;
        $review->associate('author', Account::find($user->id));
        $review->associate('reviewable', $subject)->save();
        // Refresh unloads the associated objects before serializing the public review columns.
        $review->refresh();
        return Response::json($review, 201);
    }
}
