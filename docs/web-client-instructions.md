# Web Client Instructions

Web Client គឺ customer-facing website សម្រាប់ browse products, add to cart, checkout, profile, និង order tracking។

## Technology

```text
Laravel Blade
Tailwind CSS
Alpine.js
Vite
Laravel Sanctum session/cookie auth or token auth
```

This is the recommended stack because it is fast, SEO-friendly, simple to maintain, and fits PHP/Laravel team workflow.

## Web Folder Structure

```text
web/
  resources/
    views/
      layouts/
      pages/
        home.blade.php
        products/
          index.blade.php
          show.blade.php
        cart/
          show.blade.php
        checkout/
          index.blade.php
        account/
          profile.blade.php
          orders.blade.php
      components/
        product-card.blade.php
        price.blade.php
        rating.blade.php
        pagination.blade.php
    css/
      app.css
    js/
      app.js
  routes/
    web.php
```

If web is kept inside `backend/`, use the same structure under backend Laravel project.

## Main Pages

```text
/
/products
/products/{slug}
/cart
/checkout
/login
/register
/account/profile
/account/addresses
/account/orders
/account/orders/{order_number}
/wishlist
```

## Data Loading Rule

Web page should call backend services/API and use paginated data.

Product listing must only load lightweight fields:

```text
id
name
slug
price
compare_price
primary_image
rating_avg
review_count
stock_status
```

Product detail can load heavier data:

```text
description
gallery images
variants
attributes
reviews
related products
```

## UI Components

Create reusable components:

```text
ProductCard
CategoryMenu
PriceDisplay
RatingStars
QuantityStepper
CartSummary
AddressSelector
OrderStatusBadge
EmptyState
```

## Performance Rules

```text
Use pagination for products and orders.
Lazy-load below-the-fold images.
Use optimized image sizes.
Do not render all variants in product listing.
Cache category tree and homepage sections.
Keep JavaScript small; use Alpine.js for simple interactions.
```

## User Flow

Browse:

```text
Home -> Category/Product List -> Product Detail -> Cart
```

Checkout:

```text
Cart -> Login/Register if needed -> Address -> Shipping -> Payment -> Order Success
```

Account:

```text
Profile -> Addresses -> Orders -> Order Detail -> Review
```

## Error Handling

Show clear messages for:

```text
out of stock
price changed
coupon invalid
payment failed
network/server issue
validation errors
```

## Web Design Direction

The design should be clean and shopping-focused:

```text
Fast product scanning
Clear prices
Clear stock status
Simple checkout
Mobile responsive layout
No heavy decorative layout that slows users down
```

