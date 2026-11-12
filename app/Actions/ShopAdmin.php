<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\NewProduct;
use App\Http\ProductSearch;
use App\Http\TagInput;
use App\Http\TagSelection;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tag;
use App\Shop\Pages;
use App\User;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Session\Session;
use Crab\Contracts\Storage\Storage;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Crab\Routing\FromQuery;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Range;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(409, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(401, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(404, body: ['type' => 'string'], contentType: 'text/html')]
#[Security('cookie')]
final readonly class ShopAdmin
{
    public function __construct(
        private Pages $pages,
        private CreateProduct $create,
        private SetStock $stock,
        private Tags $tags,
        private ShipOrder $ship,
        private BuildDailyReport $report,
        private Database $db,
        private Storage $storage,
        private ClockInterface $clock,
    ) {}

    #[ApiResponse(303, headers: ['Location'])]
    public function index(): Response
    {
        return Response::redirect('/admin/products', 303);
    }

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function products(ServerRequestInterface $request, User $user, #[FromQuery] ProductSearch $search): Response
    {
        $page = Product::search($search)
            ->except('details', 'created_at', 'updated_at')
            ->with('tags')
            ->paginate($search->perPage, $search->page);
        return $this->pages->render('admin.products', $request, $user, [
            'page' => $page,
            'search' => $search,
            'tags' => Tag::query()->orderBy('name')->get(),
            'pagination' => ShopCatalogue::pagination(
                $page,
                ['q' => $search->q, 'sort' => $search->sort],
                '/admin/products',
            ),
        ]);
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function productTags(int $id, TagSelection $input, Session $session): Response
    {
        $this->tags->changeProduct($id, new TagInput($input->tagIds));
        return Pages::redirect($session, '/admin/products', 'tags');
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function orderTags(int $id, TagSelection $input, Session $session): Response
    {
        $this->tags->changeOrder($id, new TagInput($input->tagIds));
        return Pages::redirect($session, '/admin/orders', 'tags');
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function create(NewProduct $input, Session $session): Response
    {
        $this->create->handle($input);
        return Pages::redirect($session, '/admin/products', 'product');
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function stock(
        int $id,
        #[Fields(['stock' => ['int', new Range(min: 0, max: 100_000)]])]
        Data $input,
        Session $session,
    ): Response {
        $this->stock->handle($id, $input);
        return Pages::redirect($session, '/admin/products', 'stock');
    }

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function orders(ServerRequestInterface $request, User $user, #[FromQuery]
        #[Range(min: 1)] int $page = 1): Response
    {
        $orders = Order::query()->with('tags')->orderBy('id', 'desc')->paginate(20, $page);
        return $this->pages->render('admin.orders', $request, $user, [
            'page' => $orders,
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function ship(int $id, Session $session): Response
    {
        $this->ship->handle($id);
        return Pages::redirect($session, '/admin/orders', 'shipped');
    }

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function reports(ServerRequestInterface $request, User $user, #[FromQuery] ?string $day = null): Response
    {
        // Yesterday in the application's zone, the one the stored dates are in, whatever zone the clock gives.
        $day ??= $this->clock
            ->now()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->modify('-1 day')
            ->format('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) !== 1) {
            throw new HttpException(422, 'Choose a report date in YYYY-MM-DD form.');
        }
        $disk = $this->storage->disk(config('shop.report_disk'));
        $report = $disk->exists($day . '.json')
            ? json_decode($disk->contents($day . '.json'), true, flags: JSON_THROW_ON_ERROR)
            : null;
        return $this->pages->render('admin.reports', $request, $user, ['day' => $day, 'report' => $report]);
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function report(#[Fields(['day' => 'string'])] Data $input, Session $session): Response
    {
        try {
            $this->report->handle($input->day);
        } catch (\InvalidArgumentException $error) {
            throw new HttpException(422, $error->getMessage(), previous: $error);
        }
        return Pages::redirect($session, '/admin/reports?day=' . rawurlencode($input->day), 'report');
    }
}
