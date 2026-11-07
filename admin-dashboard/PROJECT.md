# Admin Dashboard Project

Admin/staff application for managing the e-commerce system.

## Purpose

```text
Analytics dashboard
Product management
Category and brand management
Order management
Payment and shipment tracking
Customer management
Coupon management
Review moderation
Inventory management
Settings
```

## Stack

```text
Laravel
Filament-ready
Livewire-ready
Tailwind CSS
ApexCharts or Filament charts
PostgreSQL
Redis
```

## Setup

```bash
COMPOSER_PROCESS_TIMEOUT=2000 composer install --prefer-dist --no-interaction
npm install
php artisan key:generate
php artisan migrate
```

After Composer dependencies are installed, add Filament:

```bash
composer require filament/filament
php artisan filament:install --panels
```

## Run

```bash
php artisan serve --port=8001
npm run dev
```

## Environment

The project is configured for PostgreSQL and Redis in `.env.example`.
Use one shared database named `shop_system`.

## Admin workspace

Open `http://localhost:8001/dashboard` after signing in. The workspace reads catalog, order, customer and inventory data from the shared PostgreSQL database. Product and stock writes go through the backend API using a short-lived, server-side Sanctum token that is revoked after each request. No API credential is sent to browser JavaScript.

Keep the backend running in a second terminal:

```bash
cd ../web
php artisan serve --host=127.0.0.1 --port=8000
```

`SHOP_API_URL` defaults to `http://127.0.0.1:8000/api/v1`. In production, point this to your trusted backend using HTTPS or an isolated private network. Both applications must use the same database; only `web` owns migrations. No Filament install is required for this custom Blade interface, and its CSS/JavaScript are served directly from `public/`.

Available: date-filtered revenue/order metrics, charts, product create/edit/archive/restore, category and brand management, attributes/values, variant prices and thresholds, product image upload/primary selection/removal, inventory receipts/adjustments/returns/reservations, order confirmation/processing/shipping/delivery/cancellation, customer status and order history, coupon and shipping-method management, review moderation, payment records, refund-request approval, staff module permissions, audit history, store settings and CSV reports for sales/products/inventory/customers/payments/refunds. Existing administrators retain full access; limited staff see and can access only permitted modules.

Order confirmation requires paid status or an existing pending `cash_on_delivery` payment record and commits stock atomically. New confirmation reservations are linked to the order; cancellation before shipping releases/restocks those reservations. Older confirmed orders without inventory links require reconciliation before cancellation. Paid cancellation does not change payment status or execute a refund. Refund requests are capped by paid order value; approval is tracking only, pending a verified payment-provider integration. No gateway refund, payment capture or cash collection is performed by these controls. Shipping methods, service areas and regional fee overrides are configurable. Automatic zone/rate selection at customer checkout and carrier API integration still require the checkout/shipping integration. Messaging, traffic attribution, financial accounting and task scheduling remain outside current scope.

Successful backend admin mutations are audited without request bodies or credentials. Public product detail responses include selected variant attributes. Image uploads accept JPEG/PNG/WebP up to 2 MB; run `php artisan storage:link` in `web` so its public disk can serve images. Uploaded objects are retained when an image is removed from a product to avoid deleting historical files during an uncommitted operation; storage retention cleanup can be added separately.

Revenue is paid, non-cancelled/non-refunded order value by order creation date (UTC), not profit or settlement-date cash flow. Catalog and inventory totals are current balances, not filtered historical snapshots. UI stock edits preserve backend locking, validation and idempotency rules.

Tests: `php artisan test --compact`. `WorkspaceIntegrationTest` additionally exercises the actual backend against an isolated PostgreSQL database; it is gated by `RUN_SHOP_INTEGRATION=1`, a `DB_DATABASE` beginning with `shop_admin_ui_test_`, and `SHOP_API_URL` pointing at the backend using that same isolated database. Never point integration tests at the live store database.
