# Web Client Project

Customer-facing web application.

## Purpose

```text
Home page
Product listing
Product detail
Cart
Checkout
Customer profile
Order tracking
Wishlist
```

## Stack

```text
Laravel
Blade
Tailwind CSS
Alpine.js
Vite
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

## Run

```bash
php artisan serve --port=8000
npm run dev
```

## Environment

The project is configured for PostgreSQL and Redis in `.env.example`.
Use one shared database named `shop_system`.
