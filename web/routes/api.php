<?php

use App\Http\Controllers\Api\V1\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\InventoryController;
use App\Http\Controllers\Api\V1\Admin\ManagementController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Middleware\AuditAdminMutation;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:customer-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:customer-api-login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product:slug}', [ProductController::class, 'show']);

    Route::prefix('admin')->middleware(['auth:sanctum', 'admin', AuditAdminMutation::class])->group(function (): void {
        $management = ManagementController::class;
        Route::post('/manage/{resource}', [$management, 'store']);
        Route::patch('/manage/{resource}/{id}', [$management, 'update'])->whereNumber('id');
        Route::delete('/manage/{resource}/{id}', [$management, 'archive'])->whereNumber('id');
        Route::patch('/manage/{resource}/{id}/restore', [$management, 'restore'])->whereNumber('id');
        Route::post('/products/{product}/variants', [$management, 'saveVariant'])->whereNumber('product');
        Route::patch('/products/{product}/variants/{variant}', [$management, 'saveVariant'])->whereNumber(['product', 'variant']);
        Route::post('/products/{product}/images', [$management, 'uploadImage'])->whereNumber('product');
        Route::patch('/products/{product}/images/{image}', [$management, 'primaryImage'])->whereNumber(['product', 'image']);
        Route::delete('/products/{product}/images/{image}', [$management, 'removeImage'])->whereNumber(['product', 'image']);
        Route::patch('/orders/{order}/transition', [$management, 'transition'])->whereNumber('order');
        Route::patch('/customers/{customer}/status', [$management, 'customerStatus'])->whereNumber('customer');
        Route::post('/refunds', [$management, 'requestRefund']);
        Route::patch('/refunds/{refund}', [$management, 'reviewRefund'])->whereNumber('refund');
        Route::post('/staff', [$management, 'staff']);
        Route::patch('/staff/{staff}', [$management, 'staff'])->whereNumber('staff');
        Route::patch('/store-settings', [$management, 'storeSettings']);
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::get('/inventory/{variant}/movements', [InventoryController::class, 'movements'])->whereNumber('variant');
        Route::post('/inventory/{variant}/adjustments', [InventoryController::class, 'adjust'])->whereNumber('variant');
        Route::patch('/inventory/{variant}/threshold', [$management, 'threshold'])->whereNumber('variant');
        Route::apiResource('categories', AdminCategoryController::class);
        Route::patch('/categories/{id}/restore', [AdminCategoryController::class, 'restore']);

        Route::apiResource('brands', AdminBrandController::class);
        Route::patch('/brands/{id}/restore', [AdminBrandController::class, 'restore']);

        Route::apiResource('products', AdminProductController::class);
        Route::patch('/products/{id}/restore', [AdminProductController::class, 'restore']);
    });
});
