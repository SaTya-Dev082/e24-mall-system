<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerRecoveryController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/products', [StorefrontController::class, 'index'])->name('storefront.products.index');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('storefront.products.show');

Route::get('/cart', [CartController::class, 'index'])->name('storefront.cart.index')->block(10, 10);
Route::patch('/cart/items/{variant}', [CartController::class, 'update'])->whereNumber('variant')->middleware('throttle:60,1')->name('storefront.cart.update')->block(10, 10);
Route::post('/cart/products/{slug}', [CartController::class, 'store'])->middleware(['customer', 'throttle:60,1'])->name('storefront.cart.store')->block(10, 10);
Route::delete('/cart/items/{variant}', [CartController::class, 'remove'])->whereNumber('variant')->name('storefront.cart.remove')->block(10, 10);

Route::post('/cart/selection', [CartController::class, 'selection'])->name('storefront.cart.selection')->block(10, 10);
Route::post('/cart/remove-selected', [CartController::class, 'removeSelected'])->name('storefront.cart.remove-selected')->block(10, 10);

Route::get('/login', [CustomerAccountController::class, 'loginForm'])->name('login');
Route::get('/register', [CustomerAccountController::class, 'registerForm'])->name('register');
Route::post('/login', [CustomerAccountController::class, 'login'])->middleware('throttle:customer-login')->name('login.store')->block(10, 10);
Route::post('/register', [CustomerAccountController::class, 'register'])->middleware('throttle:customer-register')->name('register.store')->block(10, 10);
Route::post('/logout', [CustomerAccountController::class, 'logout'])->middleware('customer')->name('logout')->block(10, 10);
Route::get('/forgot-password', [CustomerRecoveryController::class, 'forgot'])->name('password.request');
Route::post('/forgot-password', [CustomerRecoveryController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [CustomerRecoveryController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [CustomerRecoveryController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
Route::middleware('customer')->group(function (): void {
    Route::get('/verify-email', [CustomerRecoveryController::class, 'verificationNotice'])->name('verification.notice');
    Route::post('/email/verification-notification', [CustomerRecoveryController::class, 'resendVerification'])->middleware('throttle:3,1')->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', [CustomerRecoveryController::class, 'verify'])->whereNumber('id')->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::get('/account/profile', [CustomerAccountController::class, 'profile'])->name('account.profile');
    Route::patch('/account/profile', [CustomerAccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [CustomerAccountController::class, 'password'])->middleware('throttle:5,1')->name('account.password');
    Route::get('/account/addresses', [CustomerAddressController::class, 'index'])->name('account.addresses');
    Route::get('/account/addresses/create', [CustomerAddressController::class, 'form'])->name('account.addresses.create');
    Route::get('/account/addresses/{address}/edit', [CustomerAddressController::class, 'form'])->whereNumber('address')->name('account.addresses.edit');
    Route::post('/account/addresses', [CustomerAddressController::class, 'save'])->name('account.addresses.store');
    Route::put('/account/addresses/{address}', [CustomerAddressController::class, 'save'])->whereNumber('address')->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [CustomerAddressController::class, 'remove'])->whereNumber('address')->name('account.addresses.remove');
    Route::get('/checkout', [CheckoutController::class, 'index'])->middleware('verified')->name('checkout.index')->block(10, 10);
});
