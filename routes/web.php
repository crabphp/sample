<?php

declare(strict_types=1);

use App\Actions\CreateProduct;
use App\Actions\ListCategories;
use App\Actions\ListMyOrders;
use App\Actions\ListProducts;
use App\Actions\Login;
use App\Actions\PlaceOrder;
use App\Actions\Ready;
use App\Actions\Register;
use App\Actions\SetStock;
use App\Actions\ShowOrder;
use App\Actions\ShowProduct;
use App\Actions\ShipOrder;
use App\Actions\WatchOrder;
use App\Http\AdminsOnly;
use App\User;
use Crab\Auth\TokenAuth;
use Crab\Auth\IssueTicket;
use Crab\Auth\Ticket;
use Crab\Http\RateLimit;
use Crab\Route;

Route::get('/', static fn (): array => ['name' => config('app.name'), 'docs' => 'See README.md for the routes.']);

// For a load balancer: /up says a worker answers, /ready that it reaches the database and the cache.
Route::get('/ready', Ready::class);

// The catalogue, open to all.
Route::get('/products', ListProducts::class);
Route::get('/products/{id}', ShowProduct::class);
Route::get('/categories', ListCategories::class);

// A few tries a minute from one address: enough for a person, not for guessing passwords.
Route::post('/register', Register::class)->middleware(new RateLimit(config('shop.register_limit'), name: 'register'));
Route::post('/login', Login::class)->middleware(new RateLimit(config('shop.login_limit'), name: 'login'));

// For whoever is logged in.
Route::websocket('/orders/{id}/live', WatchOrder::class)->middleware(Ticket::class);

Route::middleware(TokenAuth::class)->group(static function (): void {
    Route::post('/ws/ticket', IssueTicket::class);
    Route::get('/me', static fn (User $user): User => $user);
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
