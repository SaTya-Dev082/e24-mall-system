# Backend API Instructions

Backend គឺ Laravel API ដែលគ្រប់គ្រង business logic ទាំងអស់ សម្រាប់ Web, Mobile, និង Admin Dashboard។

## Technology

```text
PHP 8.3+
Laravel 11/12
PostgreSQL
Redis
Laravel Sanctum
Laravel Queue
Laravel Scheduler
Laravel API Resources
Laravel Form Requests
```

## Backend Folder Structure

```text
backend/
  app/
    Models/
    Http/
      Controllers/
        Api/
          V1/
      Requests/
      Resources/
      Middleware/
    Services/
      CartService.php
      CheckoutService.php
      StockService.php
      PaymentService.php
      AnalyticsService.php
    Actions/
      CreateOrderFromCart.php
      ReserveStock.php
      ReleaseReservedStock.php
    Jobs/
      TrackActivityJob.php
      UpdateDailySalesSummaryJob.php
      UpdateDailyProductSummaryJob.php
    Policies/
  database/
    migrations/
    seeders/
    factories/
  routes/
    api.php
```

## API Versioning

Use versioned routes:

```text
/api/v1/products
/api/v1/cart
/api/v1/checkout
/api/v1/orders
```

Route grouping:

```php
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/products', [ProductController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
    });
});
```

## PostgreSQL Migration Rules

Use PostgreSQL-friendly columns:

```php
$table->id();
$table->foreignId('product_id')->constrained()->cascadeOnDelete();
$table->string('status')->index();
$table->jsonb('metadata')->nullable();
$table->decimal('price', 12, 2);
$table->timestampsTz();
$table->softDeletesTz();
```

For non-negative stock:

```php
$table->integer('stock_quantity')->default(0);
$table->integer('reserved_quantity')->default(0);
$table->integer('sold_quantity')->default(0);
```

Add check constraints in migrations where needed:

```php
DB::statement('ALTER TABLE product_variants ADD CONSTRAINT stock_quantity_non_negative CHECK (stock_quantity >= 0)');
```

## Checkout Transaction Rule

Checkout must use `DB::transaction()` and lock stock rows.

```php
DB::transaction(function () use ($cart) {
    $variant = ProductVariant::whereKey($variantId)
        ->lockForUpdate()
        ->firstOrFail();

    $available = $variant->stock_quantity - $variant->reserved_quantity;

    if ($available < $quantity) {
        throw new OutOfStockException();
    }

    $variant->increment('reserved_quantity', $quantity);
});
```

## Service Layer Rule

Controllers must be thin:

```text
Controller -> Request Validation -> Service/Action -> Resource Response
```

Do not put checkout logic, stock logic, or payment logic directly inside controllers.

## Cache Rule

Cache read-heavy data:

```text
categories:tree
brands:active
homepage:featured-products
product:detail:{slug}
product:list:{filter_hash}
shipping-methods:active
```

Clear cache when admin updates related data.

## Queue Rule

Use queue jobs for:

```text
analytics logs
summary table updates
emails
push notifications
payment post-processing
search indexing
```

Do not process heavy analytics inside user-facing requests.

## Main API Endpoints

Auth:

```text
POST /api/v1/register
POST /api/v1/login
POST /api/v1/logout
GET /api/v1/me
PATCH /api/v1/me
```

Catalog:

```text
GET /api/v1/categories
GET /api/v1/brands
GET /api/v1/products
GET /api/v1/products/{slug}
GET /api/v1/products/{slug}/reviews
```

Cart and checkout:

```text
GET /api/v1/cart
POST /api/v1/cart/items
PATCH /api/v1/cart/items/{id}
DELETE /api/v1/cart/items/{id}
POST /api/v1/checkout
```

Orders:

```text
GET /api/v1/orders
GET /api/v1/orders/{order_number}
POST /api/v1/orders/{order_number}/cancel
```

Admin:

```text
GET /api/v1/admin/dashboard/summary
GET /api/v1/admin/orders
GET /api/v1/admin/products
```

## Implemented inventory backend

Stock is tracked per product variant in one inventory pool. `stock_quantity` is physical on-hand, `reserved_quantity` is held, and available stock is their difference. `sold_quantity` is net committed sales after restocked returns. This is not warehouse or purchase-order management.

Protected admin endpoints (active admin required):

```text
GET  /api/v1/admin/inventory?low_stock=1&per_page=20
GET  /api/v1/admin/inventory/{variant_id}/movements
POST /api/v1/admin/inventory/{variant_id}/adjustments
```

Receipt example:

```json
{
  "type": "receive",
  "quantity": 10,
  "reason": "Supplier delivery INV-1001",
  "idempotency_key": "delivery-1001-line-1"
}
```

Use `adjustment` with a signed nonzero delta for damage or count corrections. Use `return` with a positive quantity and a `reference` matching a committed reservation. Returns cannot exceed that reservation's remaining sold quantity. Reuse an idempotency key only for retries of the identical request; use a new key for each new operation. History is read-only through the API.

New variants accept initial `stock_quantity` (default zero). Existing variants must change stock through the inventory endpoint; product updates reject direct stock changes and prohibit reserved/sold quantities. Metadata updates preserve balances. Set `low_stock_threshold` through variant metadata; low-stock results are on-demand flags, not email/push alerts.

`App\Services\InventoryService` provides internal `reserve`, `commit`, `release`, `expire`, and `returnStock` operations. Reservation references must be generated by the server for a specific checkout/order. Closed references cannot be reused for a new reservation. Transactions lock the product first and then its variant. Repeated operations do not apply stock changes twice.

For a future multi-item checkout, wrap order creation and all reservations in one outer transaction and process items in ascending product/variant ID order. Commit only after the chosen verified payment/COD policy succeeds. An expired reservation cannot be committed: late payments require reconciliation/refund or an explicit new reservation. Checkout, payment webhooks and order cancellation are not implemented yet, so these internal operations are not connected to a customer checkout endpoint.

Run reservation expiry automatically:

```bash
cd web
php artisan schedule:work
```

Production must run Laravel's scheduler every minute. Manual expiry: `php artisan inventory:expire-reservations`. Existing balances are recorded as opening ledger entries during migration; pre-existing reservations have no reconstructed checkout references and require reconciliation. PostgreSQL connections use UTC to keep reservation deadlines consistent with the application.

The admin inventory UI is not implemented yet; use these endpoints in Postman. PostgreSQL contention coverage is in `InventoryConcurrencyTest`, which only runs against an isolated database whose name starts with `shop_inventory_test_`.

