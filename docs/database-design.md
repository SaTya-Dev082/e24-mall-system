ឫ# E-commerce Database Design

ឯកសារនេះជាគោលការណ៍រចនា database សម្រាប់ e-commerce system ដែលមាន Web, Mobile App និង Admin Dashboard។ Database default គឺ PostgreSQL។ Design នេះផ្អែកលើ 6 ចំណុចសំខាន់៖ normalize data, indexes, checkout transaction/stock lock, analytics background jobs, cache, និង paginated lightweight API។

## PostgreSQL Convention

ពេល implement ក្នុង Laravel migrations ត្រូវប្រើ PostgreSQL-friendly types:

```text
BIGINT UNSIGNED -> BIGINT or BIGSERIAL primary key
INT UNSIGNED -> INTEGER with CHECK value >= 0
LONGTEXT -> TEXT
ENUM -> VARCHAR + CHECK constraint, or PostgreSQL custom enum type
JSON -> JSONB
DECIMAL -> NUMERIC
TIMESTAMP -> TIMESTAMP WITH TIME ZONE where time zone matters
```

Recommended Laravel style:

```php
$table->id();
$table->foreignId('user_id')->constrained();
$table->string('status')->default('active');
$table->jsonb('metadata')->nullable();
$table->decimal('total_amount', 12, 2);
$table->softDeletesTz();
$table->timestampsTz();
```

Status fields គួរប្រើ `string + validation/config enum` សម្រាប់ version ដំបូង ដើម្បីកែបន្ថែម status ថ្មីៗបានងាយជាង PostgreSQL native enum។

## 1. Core Principles

### Normalize Core Tables

Core data ត្រូវបំបែកឲ្យបានច្បាស់ ដើម្បីកាត់បន្ថយ data ស្ទួន និងធ្វើឲ្យ maintain ងាយ។

- `users` សម្រាប់ customer account
- `addresses` សម្រាប់ delivery address
- `categories` សម្រាប់ product category
- `brands` សម្រាប់ product brand
- `products` សម្រាប់ product master data
- `product_variants` សម្រាប់ size, color, storage, option ផ្សេងៗ
- `orders` និង `order_items` សម្រាប់ purchase history

### Denormalize Where Needed

Order history ត្រូវរក្សា snapshot data ព្រោះ product អាចត្រូវបានកែពេលក្រោយ។

`order_items` ត្រូវរក្សា៖

- `product_name`
- `variant_name`
- `sku`
- `price`
- `total`

បើ admin កែ product name ឬ price ថ្មី order ចាស់នៅតែត្រឹមត្រូវ។

### Separate Operational Data From Analytics

Checkout, cart, payment ត្រូវលឿន និងស្រាល។ Analytics មិនគួរ query ធ្ងន់ៗលើ request របស់ user ទេ។

លំហូរល្អ៖

```text
User action
-> write small log
-> queue job
-> update summary table
-> admin dashboard reads summary table
```

## 2. Main Tables

### users

```sql
id BIGINT UNSIGNED PRIMARY KEY
name VARCHAR(255)
email VARCHAR(255) UNIQUE
phone VARCHAR(50) UNIQUE NULL
password VARCHAR(255)
avatar VARCHAR(255) NULL
gender VARCHAR(20) NULL
date_of_birth DATE NULL
status ENUM('active', 'inactive', 'blocked') DEFAULT 'active'
email_verified_at TIMESTAMP NULL
phone_verified_at TIMESTAMP NULL
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(email)
UNIQUE(phone)
INDEX(status)
INDEX(created_at)
```

### addresses

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED
full_name VARCHAR(255)
phone VARCHAR(50)
province VARCHAR(100)
city VARCHAR(100) NULL
district VARCHAR(100) NULL
commune VARCHAR(100) NULL
village VARCHAR(100) NULL
street_address TEXT
postal_code VARCHAR(30) NULL
latitude DECIMAL(10, 7) NULL
longitude DECIMAL(10, 7) NULL
is_default BOOLEAN DEFAULT FALSE
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(user_id, is_default)
```

### categories

```sql
id BIGINT UNSIGNED PRIMARY KEY
parent_id BIGINT UNSIGNED NULL
name VARCHAR(255)
slug VARCHAR(255) UNIQUE
image VARCHAR(255) NULL
description TEXT NULL
status ENUM('active', 'inactive') DEFAULT 'active'
sort_order INT DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(slug)
INDEX(parent_id)
INDEX(status)
INDEX(sort_order)
```

### brands

```sql
id BIGINT UNSIGNED PRIMARY KEY
name VARCHAR(255)
slug VARCHAR(255) UNIQUE
logo VARCHAR(255) NULL
description TEXT NULL
status ENUM('active', 'inactive') DEFAULT 'active'
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(slug)
INDEX(status)
```

### products

```sql
id BIGINT UNSIGNED PRIMARY KEY
category_id BIGINT UNSIGNED
brand_id BIGINT UNSIGNED NULL
name VARCHAR(255)
slug VARCHAR(255) UNIQUE
sku VARCHAR(100) UNIQUE NULL
short_description TEXT NULL
description LONGTEXT NULL
price DECIMAL(12, 2)
compare_price DECIMAL(12, 2) NULL
cost_price DECIMAL(12, 2) NULL
stock_status ENUM('in_stock', 'out_of_stock', 'preorder') DEFAULT 'in_stock'
is_featured BOOLEAN DEFAULT FALSE
status ENUM('draft', 'active', 'inactive') DEFAULT 'draft'
rating_avg DECIMAL(3, 2) DEFAULT 0
review_count INT UNSIGNED DEFAULT 0
sold_count INT UNSIGNED DEFAULT 0
view_count INT UNSIGNED DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(slug)
UNIQUE(sku)
INDEX(category_id)
INDEX(brand_id)
INDEX(status)
INDEX(price)
INDEX(created_at)
INDEX(category_id, status)
INDEX(brand_id, status)
INDEX(status, is_featured)
INDEX(status, sold_count)
INDEX(status, rating_avg)
```

Note: `description` មិនគួរទាញទៅ product listing ទេ។ Listing API គួរទាញតែ field ស្រាលៗ។

### product_images

```sql
id BIGINT UNSIGNED PRIMARY KEY
product_id BIGINT UNSIGNED
image_url VARCHAR(255)
alt_text VARCHAR(255) NULL
is_primary BOOLEAN DEFAULT FALSE
sort_order INT DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(product_id)
INDEX(product_id, is_primary)
INDEX(product_id, sort_order)
```

### product_variants

```sql
id BIGINT UNSIGNED PRIMARY KEY
product_id BIGINT UNSIGNED
sku VARCHAR(100) UNIQUE
name VARCHAR(255)
price DECIMAL(12, 2)
compare_price DECIMAL(12, 2) NULL
stock_quantity INT UNSIGNED DEFAULT 0
reserved_quantity INT UNSIGNED DEFAULT 0
sold_quantity INT UNSIGNED DEFAULT 0
status ENUM('active', 'inactive') DEFAULT 'active'
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(sku)
INDEX(product_id)
INDEX(status)
INDEX(product_id, status)
```

Available stock:

```text
stock_quantity - reserved_quantity
```

### attributes

```sql
id BIGINT UNSIGNED PRIMARY KEY
name VARCHAR(100)
created_at TIMESTAMP
updated_at TIMESTAMP
```

### attribute_values

```sql
id BIGINT UNSIGNED PRIMARY KEY
attribute_id BIGINT UNSIGNED
value VARCHAR(100)
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(attribute_id)
UNIQUE(attribute_id, value)
```

### product_variant_values

```sql
id BIGINT UNSIGNED PRIMARY KEY
product_variant_id BIGINT UNSIGNED
attribute_id BIGINT UNSIGNED
attribute_value_id BIGINT UNSIGNED
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(product_variant_id)
INDEX(attribute_id)
INDEX(attribute_value_id)
UNIQUE(product_variant_id, attribute_id)
```

## 3. Cart Tables

### carts

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED NULL
session_id VARCHAR(255) NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(session_id)
```

### cart_items

```sql
id BIGINT UNSIGNED PRIMARY KEY
cart_id BIGINT UNSIGNED
product_id BIGINT UNSIGNED
product_variant_id BIGINT UNSIGNED NULL
quantity INT UNSIGNED
price DECIMAL(12, 2)
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(cart_id)
INDEX(product_id)
INDEX(product_variant_id)
UNIQUE(cart_id, product_id, product_variant_id)
```

`price` គឺ snapshot នៅពេល add to cart ប៉ុន្តែ checkout ត្រូវ validate price ចុងក្រោយម្តងទៀត។

## 4. Order And Payment Tables

### orders

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED
order_number VARCHAR(50) UNIQUE
address_id BIGINT UNSIGNED
coupon_id BIGINT UNSIGNED NULL
subtotal DECIMAL(12, 2)
discount_amount DECIMAL(12, 2) DEFAULT 0
shipping_fee DECIMAL(12, 2) DEFAULT 0
tax_amount DECIMAL(12, 2) DEFAULT 0
total_amount DECIMAL(12, 2)
payment_status ENUM('unpaid', 'pending', 'paid', 'failed', 'refunded') DEFAULT 'unpaid'
order_status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending'
note TEXT NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
UNIQUE(order_number)
INDEX(user_id)
INDEX(order_status)
INDEX(payment_status)
INDEX(created_at)
INDEX(user_id, created_at)
INDEX(order_status, created_at)
INDEX(payment_status, created_at)
```

### order_items

```sql
id BIGINT UNSIGNED PRIMARY KEY
order_id BIGINT UNSIGNED
product_id BIGINT UNSIGNED
product_variant_id BIGINT UNSIGNED NULL
product_name VARCHAR(255)
variant_name VARCHAR(255) NULL
sku VARCHAR(100)
quantity INT UNSIGNED
price DECIMAL(12, 2)
total DECIMAL(12, 2)
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(order_id)
INDEX(product_id)
INDEX(product_variant_id)
```

### payments

```sql
id BIGINT UNSIGNED PRIMARY KEY
order_id BIGINT UNSIGNED
user_id BIGINT UNSIGNED
payment_method VARCHAR(50)
transaction_id VARCHAR(255) NULL
amount DECIMAL(12, 2)
currency VARCHAR(10) DEFAULT 'USD'
status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending'
paid_at TIMESTAMP NULL
gateway_response JSON NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(order_id)
INDEX(user_id)
INDEX(transaction_id)
INDEX(status)
INDEX(created_at)
```

### order_status_histories

```sql
id BIGINT UNSIGNED PRIMARY KEY
order_id BIGINT UNSIGNED
from_status VARCHAR(50) NULL
to_status VARCHAR(50)
note TEXT NULL
changed_by BIGINT UNSIGNED NULL
created_at TIMESTAMP
```

Indexes:

```sql
INDEX(order_id)
INDEX(to_status)
INDEX(created_at)
```

## 5. Shipping Tables

### shipping_methods

```sql
id BIGINT UNSIGNED PRIMARY KEY
name VARCHAR(255)
description TEXT NULL
fee DECIMAL(12, 2)
estimated_days VARCHAR(100) NULL
status ENUM('active', 'inactive') DEFAULT 'active'
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(status)
```

### shipments

```sql
id BIGINT UNSIGNED PRIMARY KEY
order_id BIGINT UNSIGNED
shipping_method_id BIGINT UNSIGNED
tracking_number VARCHAR(255) NULL
carrier_name VARCHAR(255) NULL
status ENUM('pending', 'shipped', 'in_transit', 'delivered', 'failed', 'returned') DEFAULT 'pending'
shipped_at TIMESTAMP NULL
delivered_at TIMESTAMP NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(order_id)
INDEX(shipping_method_id)
INDEX(tracking_number)
INDEX(status)
```

## 6. Marketing And User Engagement

### wishlists

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED
product_id BIGINT UNSIGNED
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
UNIQUE(user_id, product_id)
INDEX(product_id)
```

### coupons

```sql
id BIGINT UNSIGNED PRIMARY KEY
code VARCHAR(100) UNIQUE
type ENUM('fixed', 'percentage', 'free_shipping')
value DECIMAL(12, 2)
min_order_amount DECIMAL(12, 2) DEFAULT 0
max_discount_amount DECIMAL(12, 2) NULL
usage_limit INT UNSIGNED NULL
used_count INT UNSIGNED DEFAULT 0
start_date TIMESTAMP NULL
end_date TIMESTAMP NULL
status ENUM('active', 'inactive') DEFAULT 'active'
created_at TIMESTAMP
updated_at TIMESTAMP
deleted_at TIMESTAMP NULL
```

Indexes:

```sql
UNIQUE(code)
INDEX(status)
INDEX(start_date, end_date)
```

### coupon_usages

```sql
id BIGINT UNSIGNED PRIMARY KEY
coupon_id BIGINT UNSIGNED
user_id BIGINT UNSIGNED
order_id BIGINT UNSIGNED
used_at TIMESTAMP
```

Indexes:

```sql
INDEX(coupon_id)
INDEX(user_id)
INDEX(order_id)
UNIQUE(coupon_id, order_id)
```

### reviews

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED
product_id BIGINT UNSIGNED
order_id BIGINT UNSIGNED NULL
rating TINYINT UNSIGNED
comment TEXT NULL
status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(product_id)
INDEX(status)
INDEX(product_id, status)
UNIQUE(user_id, product_id, order_id)
```

## 7. Analytics Tables

### product_views

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED NULL
session_id VARCHAR(255) NULL
product_id BIGINT UNSIGNED
ip_address VARCHAR(45) NULL
user_agent TEXT NULL
created_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(session_id)
INDEX(product_id)
INDEX(created_at)
INDEX(product_id, created_at)
```

### search_logs

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED NULL
session_id VARCHAR(255) NULL
keyword VARCHAR(255)
result_count INT UNSIGNED DEFAULT 0
created_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(session_id)
INDEX(keyword)
INDEX(created_at)
```

### activity_logs

```sql
id BIGINT UNSIGNED PRIMARY KEY
user_id BIGINT UNSIGNED NULL
session_id VARCHAR(255) NULL
action VARCHAR(100)
entity_type VARCHAR(100) NULL
entity_id BIGINT UNSIGNED NULL
metadata JSON NULL
created_at TIMESTAMP
```

Indexes:

```sql
INDEX(user_id)
INDEX(session_id)
INDEX(action)
INDEX(entity_type, entity_id)
INDEX(created_at)
```

Recommended actions:

```text
view_product
add_to_cart
remove_from_cart
checkout_started
order_created
payment_success
payment_failed
search
wishlist_added
review_created
```

## 8. Summary Tables For Admin Dashboard

Admin dashboard មិនគួរ query raw orders/logs រាប់លាន records រាល់ request ទេ។ ត្រូវប្រើ summary tables ដែល update ដោយ queue jobs ឬ scheduled jobs។

### daily_sales_summaries

```sql
id BIGINT UNSIGNED PRIMARY KEY
summary_date DATE UNIQUE
orders_count INT UNSIGNED DEFAULT 0
gross_sales DECIMAL(14, 2) DEFAULT 0
discount_total DECIMAL(14, 2) DEFAULT 0
shipping_total DECIMAL(14, 2) DEFAULT 0
tax_total DECIMAL(14, 2) DEFAULT 0
net_sales DECIMAL(14, 2) DEFAULT 0
cancelled_orders_count INT UNSIGNED DEFAULT 0
refunded_total DECIMAL(14, 2) DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
UNIQUE(summary_date)
```

### daily_product_summaries

```sql
id BIGINT UNSIGNED PRIMARY KEY
summary_date DATE
product_id BIGINT UNSIGNED
views_count INT UNSIGNED DEFAULT 0
cart_adds_count INT UNSIGNED DEFAULT 0
orders_count INT UNSIGNED DEFAULT 0
units_sold INT UNSIGNED DEFAULT 0
revenue DECIMAL(14, 2) DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
UNIQUE(summary_date, product_id)
INDEX(product_id)
INDEX(summary_date)
```

### daily_customer_summaries

```sql
id BIGINT UNSIGNED PRIMARY KEY
summary_date DATE UNIQUE
new_customers_count INT UNSIGNED DEFAULT 0
active_customers_count INT UNSIGNED DEFAULT 0
returning_customers_count INT UNSIGNED DEFAULT 0
created_at TIMESTAMP
updated_at TIMESTAMP
```

Indexes:

```sql
UNIQUE(summary_date)
```

## 9. Checkout Transaction Flow

Checkout ត្រូវប្រើ database transaction និង row lock ដើម្បីការពារ stock ខុស ពេលមាន users ច្រើន checkout ព្រមគ្នា។

```text
BEGIN TRANSACTION

1. Load cart and cart_items
2. Lock selected product_variants FOR UPDATE
3. Check variant status
4. Check available stock = stock_quantity - reserved_quantity
5. Validate price/coupon/shipping
6. Create order
7. Create order_items with product snapshot
8. Create payment record
9. Increase reserved_quantity
10. Clear cart

COMMIT
```

បើមាន error:

```text
ROLLBACK
```

Payment success callback:

```text
BEGIN TRANSACTION

1. Verify payment gateway signature
2. Lock payment row
3. Lock order row
4. If already paid, return success safely
5. Update payment status = paid
6. Update order payment_status = paid
7. Update order_status = confirmed
8. For each item:
   - stock_quantity -= quantity
   - reserved_quantity -= quantity
   - sold_quantity += quantity
9. Dispatch analytics and notification jobs

COMMIT
```

Payment failed/expired:

```text
BEGIN TRANSACTION

1. Update payment status = failed
2. Update order payment_status = failed
3. Release reserved stock:
   - reserved_quantity -= quantity

COMMIT
```

## 10. Cache Strategy

Read-heavy data គួរប្រើ Redis ឬ Laravel Cache។

Recommended cache keys:

```text
categories:tree
brands:active
homepage:featured-products
homepage:new-products
product:detail:{slug}
product:list:{hash_of_filters}
shipping-methods:active
settings:public
```

Recommended TTL:

```text
categories: 1 hour
brands: 1 hour
homepage sections: 5-15 minutes
product detail: 5 minutes
product list: 1-5 minutes
shipping methods: 1 hour
settings: 1 hour
```

Cache invalidation:

```text
Admin updates product -> clear product detail/list/homepage cache
Admin updates category -> clear category tree/product list cache
Admin updates shipping -> clear shipping methods cache
Admin updates settings -> clear settings cache
```

## 11. API Response Rules

Web និង Mobile គួរប្រើ API ដូចគ្នា។ Response ត្រូវស្រាល ពិសេសសម្រាប់ mobile។

### Product Listing API

Endpoint:

```text
GET /api/products?category=phones&brand=apple&sort=latest&limit=20&cursor=xxx
```

Return only:

```text
id
name
slug
price
compare_price
primary_image
rating_avg
review_count
sold_count
stock_status
```

Do not return:

```text
description
all images
all variants
raw reviews
analytics logs
```

### Product Detail API

Endpoint:

```text
GET /api/products/{slug}
```

Return:

```text
product detail
gallery images
variants
attributes
rating summary
latest approved reviews with pagination
related products, limited
```

### Pagination

Use cursor pagination for mobile infinite scroll:

```text
GET /api/products?limit=20&cursor=eyJpZCI6...
```

Use page pagination where page number matters:

```text
GET /api/orders?page=1&per_page=20
```

## 12. Recommended API Endpoints

Auth:

```text
POST /api/register
POST /api/login
POST /api/logout
GET /api/me
PATCH /api/me
```

Catalog:

```text
GET /api/categories
GET /api/brands
GET /api/products
GET /api/products/{slug}
GET /api/products/{slug}/reviews
```

Cart:

```text
GET /api/cart
POST /api/cart/items
PATCH /api/cart/items/{id}
DELETE /api/cart/items/{id}
```

Checkout:

```text
POST /api/checkout
POST /api/coupons/validate
GET /api/shipping-methods
```

Orders:

```text
GET /api/orders
GET /api/orders/{order_number}
POST /api/orders/{order_number}/cancel
```

Wishlist:

```text
GET /api/wishlist
POST /api/wishlist
DELETE /api/wishlist/{product_id}
```

Reviews:

```text
POST /api/reviews
PATCH /api/reviews/{id}
DELETE /api/reviews/{id}
```

Analytics:

```text
POST /api/events
```

## 13. Laravel Implementation Notes

Recommended stack:

```text
Laravel Sanctum for web/mobile API auth
MySQL or PostgreSQL for main database
Redis for cache, queue, and session
Laravel Queue for analytics, email, notification, summary updates
Laravel Scheduler for daily summary rebuilding
Laravel Scout + Meilisearch later for advanced product search
Object storage for product images
```

Recommended Laravel folders:

```text
app/
  Models/
  Http/Controllers/Api/
  Http/Requests/
  Services/
    CartService.php
    CheckoutService.php
    StockService.php
    PaymentService.php
  Jobs/
    TrackProductViewJob.php
    UpdateDailySalesSummaryJob.php
    UpdateDailyProductSummaryJob.php
  Actions/
    CreateOrderFromCart.php
    ReserveStock.php
    ReleaseReservedStock.php
```

## 14. Build Order

Recommended development order:

```text
1. Auth and users
2. Categories, brands, products, variants, images
3. Product listing/detail API with pagination
4. Cart
5. Address book
6. Checkout transaction with stock lock
7. Payment record and callback flow
8. Orders and order tracking
9. Reviews and wishlist
10. Analytics logs
11. Queue jobs and summary tables
12. Admin dashboard analytics
13. Cache optimization
14. Search engine integration
```

## 15. Version 1 Minimum Scope

If we want to launch a first stable version, start with:

```text
users
addresses
categories
brands
products
product_images
product_variants
carts
cart_items
orders
order_items
payments
shipping_methods
shipments
wishlists
reviews
```

Then add:

```text
coupons
coupon_usages
product_views
search_logs
activity_logs
daily_sales_summaries
daily_product_summaries
daily_customer_summaries
```
