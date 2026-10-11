<?php

declare(strict_types=1);

use App\Actions\CreateProduct;
use App\Actions\ListCategories;
use App\Actions\ListMyOrders;
use App\Actions\ListProducts;
use App\Actions\Login;
use App\Actions\PlaceOrder;
use App\Actions\Register;
use App\Actions\SetStock;
use App\Actions\ShowOrder;
use App\Actions\ShowProduct;
use App\Http\AdminsOnly;
use App\User;
use Crab\Auth\TokenAuth;
use Crab\Http\RateLimit;
use Crab\Route;

Route::get('/', static fn (): array => ['name' => config('app.name'), 'docs' => 'See README.md for the routes.']);

// The catalogue, open to all.
Route::get('/products', ListProducts::class);
Route::get('/products/{id}', ShowProduct::class);
Route::get('/categories', ListCategories::class);

// A few tries a minute from one address: enough for a person, not for guessing passwords.
Route::post('/register', Register::class, middleware: [new RateLimit(10, name: 'register')]);
Route::post('/login', Login::class, middleware: [new RateLimit(10, name: 'login')]);

// For whoever is logged in.
Route::group(middleware: [TokenAuth::class], routes: static function (): void {
    Route::get('/me', static fn (User $user): User => $user);
    Route::get('/orders', ListMyOrders::class);
    Route::post('/orders', PlaceOrder::class);
    Route::get('/orders/{id}', ShowOrder::class);

    // For administrators.
    Route::group(middleware: [AdminsOnly::class], routes: static function (): void {
        Route::post('/products', CreateProduct::class);
        Route::put('/products/{id}/stock', SetStock::class);
    });
});
