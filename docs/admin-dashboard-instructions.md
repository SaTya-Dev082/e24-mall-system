# Admin Dashboard Instructions

Admin Dashboard គឺ system សម្រាប់ admin/staff គ្រប់គ្រង products, orders, customers, coupons, inventory, និង analytics។

## Technology

Recommended:

```text
Laravel Filament
Livewire
Tailwind CSS
ApexCharts or Filament charts
PostgreSQL
Redis Queue
```

Filament គួររស់នៅក្នុង Laravel backend project ព្រោះវាប្រើ Models, Policies, Services, និង database ដូចគ្នា។ Folder `admin-dashboard/` អាចរក្សា design notes និង dashboard specs។

## Admin Modules

```text
Dashboard analytics
Products
Categories
Brands
Product variants
Orders
Payments
Shipments
Customers
Reviews
Coupons
Inventory
Settings
Admin users/roles
```

## Permission Model

Use roles and permissions:

```text
super_admin
admin
manager
support
inventory_staff
```

Example permissions:

```text
view_dashboard
manage_products
manage_orders
manage_customers
manage_coupons
manage_inventory
manage_settings
```

Recommended package:

```text
spatie/laravel-permission
```

## Dashboard Metrics

Dashboard should read from summary tables where possible:

```text
daily_sales_summaries
daily_product_summaries
daily_customer_summaries
```

Top cards:

```text
Today revenue
Today orders
Pending orders
Low stock products
New customers
Conversion events
```

Charts:

```text
Sales by day
Orders by status
Top selling products
Most viewed products
Search keywords
Customer growth
```

## CRUD Resource Rules

Products:

```text
Use tabs/sections for basic info, pricing, inventory, images, variants, SEO.
Do not delete products permanently; use soft delete or inactive status.
Clear product cache after create/update/delete.
```

Orders:

```text
Admin can update order status.
Every status change writes order_status_histories.
Payment status should only change from trusted payment flow or authorized admin action.
```

Inventory:

```text
Show stock_quantity, reserved_quantity, available stock, sold_quantity.
Warn when available stock is low.
Use service class for stock adjustments.
```

Reviews:

```text
Review moderation: pending, approved, rejected.
Approved reviews update product rating_avg and review_count by job.
```

Coupons:

```text
Validate date range, usage limit, minimum order amount.
Track usage in coupon_usages.
```

## Admin Performance Rules

```text
Use table pagination.
Use filters with indexed columns.
Avoid loading all orders/products at once.
Use summary tables for dashboard charts.
Use queue jobs for exports.
Cache dashboard summary for short TTL.
```

## Admin Routes

Recommended:

```text
/admin
/admin/products
/admin/categories
/admin/brands
/admin/orders
/admin/customers
/admin/coupons
/admin/reviews
/admin/analytics
/admin/settings
```

## Audit Rule

Important admin actions should be logged:

```text
product updated
order status changed
payment manually adjusted
coupon created/updated
stock adjusted
user blocked/unblocked
```

Use `activity_logs` or a dedicated admin audit table later if needed.

