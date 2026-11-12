<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Http\TagInput;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tag;
use App\TagOperation;
use App\User;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Page;
use Crab\Database\DatabaseException;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Routing\FromQuery;
use Crab\Tide\ModelQuery;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Pattern;
use Crab\Validation\Range;

#[ApiResponse(404, description: 'No such tag or subject, or the order belongs to another customer.')]
final readonly class Tags
{
    public function __construct(
        private Database $db,
    ) {}

    #[ApiResponse(200, body: OpenApiSchemas::TAG_PAGE)]
    public function handle(#[FromQuery]
        #[Range(min: 1)] int $page = 1): Page
    {
        return Tag::query()->orderBy('name')->orderBy('id')->paginate(50, $page);
    }

    #[ApiResponse(201, body: OpenApiSchemas::TAG)]
    #[ApiResponse(409, description: 'That tag name already exists.')]
    public function create(
        #[Fields(['name' => ['string', new Pattern('^[a-z0-9][a-z0-9-]{1,39}$')]])]
        Data $input,
    ): Response {
        $tag = new Tag();
        $tag->name = $input->name;
        try {
            $tag->save();
        } catch (DatabaseException $error) {
            if ($error->sqlState() === '23000' && Tag::query()->where('name', $input->name)->exists()) {
                throw new HttpException(409, 'That tag already exists.', previous: $error);
            }
            throw $error;
        }
        return Response::json($tag, 201);
    }

    #[ApiResponse(200, body: OpenApiSchemas::TAG_SUMMARY)]
    public function show(int $id): array
    {
        $tag = Tag::find($id) ?? throw new HttpException(404, 'No such tag.');
        // These are totals of model columns. The related-row condition stays on every aggregate.
        $products = Product::query()->whereHas('tags', static fn(ModelQuery $query): ModelQuery => $query->where(
            'id',
            $id,
        ));
        $orders = Order::query()->whereHas('tags', static fn(ModelQuery $query): ModelQuery => $query->where(
            'id',
            $id,
        ));
        return [
            'tag' => $tag,
            'products' => $products->count(),
            'stock' => $products->sum('stock'),
            'average_price' => $products->avg('price'),
            'lowest_price' => $products->min('price'),
            'highest_price' => $products->max('price'),
            'orders' => $orders->count(),
            'order_total' => $orders->sum('total'),
            'latest_order_at' => $orders->max('created_at')?->format(DATE_ATOM),
            'latest_order_status' => (clone $orders)->orderBy('id', 'desc')->value('status'),
            // Read the inverse polymorphic relations too; previews are deliberately bounded.
            'product_names' => $tag->related('products')->orderBy('id')->limit(20)->pluck('name'),
            'order_statuses' => $tag->related('orders')->orderBy('id', 'desc')->limit(20)->pluck('status'),
        ];
    }

    #[ApiResponse(200, body: OpenApiSchemas::TAG_LIST)]
    public function product(int $id): array
    {
        $product = Product::query()->with('tags')->find($id) ?? throw new HttpException(404, 'No such product.');
        return $product->tags;
    }

    #[ApiResponse(200, body: OpenApiSchemas::TAG_LIST)]
    public function order(User $user, int $id): array
    {
        $order = Order::query()->where('user_id', $user->id)->with('tags')->find($id) ?? throw new HttpException(
            404,
            'No such order.',
        );
        return $order->tags;
    }

    #[ApiResponse(200, body: OpenApiSchemas::TAG_LIST)]
    #[ApiResponse(422, description: 'One or more tag IDs do not exist.')]
    public function changeProduct(int $id, TagInput $input): array
    {
        $product = Product::find($id) ?? throw new HttpException(404, 'No such product.');
        return $this->change($product, $input);
    }

    #[ApiResponse(200, body: OpenApiSchemas::TAG_LIST)]
    #[ApiResponse(422, description: 'One or more tag IDs do not exist.')]
    public function changeOrder(int $id, TagInput $input): array
    {
        $order = Order::find($id) ?? throw new HttpException(404, 'No such order.');
        return $this->change($order, $input);
    }

    /** @return list<Tag> */
    private function change(Product|Order $subject, TagInput $input): array
    {
        return $this->db->transaction(static function () use ($subject, $input): array {
            $ids = array_values(array_unique($input->tagIds));
            if (Tag::query()->whereIn('id', $ids)->count() !== count($ids)) {
                throw new HttpException(422, 'One or more tags do not exist.');
            }
            match ($input->operation) {
                TagOperation::Attach => $subject->attach('tags', $ids),
                TagOperation::Detach => $subject->detach('tags', $ids),
                TagOperation::Sync => $subject->sync('tags', $ids),
            };
            // Link writes unload a previously loaded relation; read the new list explicitly.
            $subject::load([$subject], 'tags');
            return $subject->tags;
        });
    }
}
