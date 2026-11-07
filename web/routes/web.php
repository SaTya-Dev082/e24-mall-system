<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'index'])->name('storefront.products.index');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');

Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.index');
Route::post('/cart/products/{slug}', [CartController::class, 'store'])->middleware('throttle:60,1')->name('storefront.cart.store');
Route::delete('/cart/items/{variant}', [CartController::class, 'remove'])->whereNumber('variant')->name('storefront.cart.remove');
