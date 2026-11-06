<?php

declare(strict_types=1);

use App\Actions\AskAssistant;
use App\Actions\ChatWithAssistant;
use App\Actions\CreateProduct;
use App\Actions\ExportProducts;
use App\Actions\IssueTicket;
use App\Actions\ListCategories;
use App\Actions\ListMyOrders;
use App\Actions\ListProducts;
use App\Actions\Login;
use App\Actions\PlaceOrder;
use App\Actions\PretendModel;
use App\Actions\Ready;
use App\Actions\Register;
use App\Actions\SetStock;
use App\Actions\ShipOrder;
use App\Actions\ShopCart;
use App\Actions\ShopCatalogue;
use App\Actions\ShopCheckout;
use App\Actions\ShopLogin;
use App\Actions\ShopLogout;
use App\Actions\ShopOrder;
use App\Actions\ShopOrders;
use App\Actions\ShopProduct;
use App\Actions\ShowOrder;
use App\Actions\ShowProduct;
use App\Actions\WatchOrder;
use App\Actions\WatchStock;
use App\Http\AdminsOnly;
use App\Http\OpenApiSchemas;
use App\User;
use Crab\Auth\CookieAuth;
use Crab\Auth\Ticket;
use Crab\Auth\TokenAuth;
use Crab\Http\RateLimit;
use Crab\OpenApi\Attributes\Response;
use Crab\Route;

Route::get('/assets/shop-v1/{file}', \App\Actions\ShopAssets::class);
Route::get('/openapi.json', [\App\Actions\ShopAssets::class, 'openapi']);
Route::get('/shop', [\App\Actions\ShopLegacy::class, 'index']);
Route::get('/shop/{path:.*}', \App\Actions\ShopLegacy::class);
Route::post('/shop/{path:.*}', [\App\Actions\ShopLegacy::class, 'post'])->middleware(new CookieAuth(required: false));

Route::middleware(new CookieAuth(required: false))->group(static function (): void {
    Route::get('/', \App\Actions\ShopHome::class);
    Route::get('/products', ShopCatalogue::class);
    Route::get('/products/{id}', ShopProduct::class);
    Route::get('/cart', ShopCart::class);
    Route::post('/cart/{id}/add', [ShopCart::class, 'add']);
    Route::post('/cart/{id}/update', [ShopCart::class, 'update']);
    Route::post('/cart/{id}/remove', [ShopCart::class, 'remove']);
    Route::get('/login', [ShopLogin::class, 'show']);
    Route::post('/login', ShopLogin::class)->middleware(
        new RateLimit((int) config('shop.login_limit'), name: 'shop-login'),
    );
    Route::get('/register', [\App\Actions\ShopRegister::class, 'show']);
    Route::post('/register', \App\Actions\ShopRegister::class)->middleware(
        new RateLimit((int) config('shop.register_limit'), name: 'shop-register'),
    );
    Route::post('/logout', ShopLogout::class);
    Route::get('/orders/{id}', ShopOrder::class);
    Route::get('/assistant', [\App\Actions\ShopAssistant::class, 'show']);
    Route::post('/assistant', \App\Actions\ShopAssistant::class);
    Route::post('/assistant/stream', [\App\Actions\ShopAssistant::class, 'stream']);
});
Route::middleware(CookieAuth::class)->group(static function (): void {
    Route::get('/checkout', [ShopCheckout::class, 'show']);
    Route::post('/checkout', ShopCheckout::class);
    Route::get('/orders', ShopOrders::class);
    Route::websocket('/orders/{id}/live', WatchOrder::class);
    Route::post('/assistant/chat', [\App\Actions\ShopAssistant::class, 'chat']);
    Route::middleware(AdminsOnly::class)->group(static function (): void {
        Route::get('/admin', [\App\Actions\ShopAdmin::class, 'index']);
        Route::get('/admin/products', [\App\Actions\ShopAdmin::class, 'products']);
        Route::post('/admin/products', [\App\Actions\ShopAdmin::class, 'create']);
        Route::post('/admin/products/{id}/stock', [\App\Actions\ShopAdmin::class, 'stock']);
        Route::get('/admin/orders', [\App\Actions\ShopAdmin::class, 'orders']);
        Route::post('/admin/orders/{id}/ship', [\App\Actions\ShopAdmin::class, 'ship']);
        Route::get('/admin/reports', [\App\Actions\ShopAdmin::class, 'reports']);
        Route::post('/admin/reports', [\App\Actions\ShopAdmin::class, 'report']);
    });
});

Route::prefix('/api')->group(static function (): void {
    Route::get('/', #[Response(200, body: OpenApiSchemas::HOME)]
    static fn(): array => [
        'name' => config('app.name'),
        'docs' => 'See README.md for the routes.',
    ]);

    // For a load balancer: /up says a worker answers, /ready that it reaches the database and the cache.
    Route::get('/ready', Ready::class);

    // The catalogue, open to all.
    Route::get('/products', ListProducts::class);
    Route::get('/products.csv', ExportProducts::class);
    Route::get('/products/{id}', ShowProduct::class);
    Route::get('/products/{id}/stock', WatchStock::class); // server-sent events, live
    Route::get('/categories', ListCategories::class);

    // The assistant streams a model's answer as it comes; the model is pretend, as the warehouse is.
    Route::post('/assistant', AskAssistant::class);
    Route::post('/model/chat', PretendModel::class);

    // A few tries a minute from one address: enough for a person, not for guessing passwords.
    Route::post('/register', Register::class)->middleware(
        new RateLimit((int) config('shop.register_limit'), name: 'register'),
    );
    Route::post('/login', Login::class)->middleware(new RateLimit((int) config('shop.login_limit'), name: 'login'));

    // For whoever is logged in.
    Route::websocket('/orders/{id}/live', WatchOrder::class)->middleware(Ticket::class);

    Route::middleware(TokenAuth::class)->group(static function (): void {
        Route::post('/ws/ticket', IssueTicket::class);
        Route::get('/me', #[Response(200, body: OpenApiSchemas::USER)] static fn(User $user): User => $user);
        Route::post('/assistant/chat', ChatWithAssistant::class); // your own assistant, an actor that remembers
        Route::get('/orders', ListMyOrders::class);
        Route::post('/orders', PlaceOrder::class);
        Route::get('/orders/{id}', ShowOrder::class);

        // For administrators.
        Route::middleware(AdminsOnly::class)->group(static function (): void {
            Route::post('/products', CreateProduct::class);
            Route::put('/products/{id}/stock', SetStock::class);
            Route::put('/orders/{id}/ship', ShipOrder::class);
        });
    });
});
