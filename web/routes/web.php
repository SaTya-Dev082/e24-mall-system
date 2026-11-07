<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'index'])->name('storefront.products.index');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');
