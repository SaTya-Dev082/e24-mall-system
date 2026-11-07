# E-commerce System Architecture Instructions

ឯកសារនេះជាគំរូរួមសម្រាប់បង្កើត project ទាំង 4 ផ្នែក៖ Backend API, Web Client, Mobile App, និង Admin Dashboard។ Database ប្រើ PostgreSQL។

## Main Stack

```text
Backend API: Laravel + PHP
Database: PostgreSQL
Cache/Queue/Session: Redis
Web Client: Laravel Blade + Tailwind CSS + Alpine.js + Vite
Mobile App: Flutter + Riverpod + Dio + GoRouter
Admin Dashboard: Laravel Filament + Livewire + Tailwind CSS + ApexCharts
Auth: Laravel Sanctum
Storage: Local for development, S3-compatible storage for production
Search later: Laravel Scout + Meilisearch
```

## Repository Structure

Recommended folder structure:

```text
shop/
  backend/
  web/
  mobile/
  admin-dashboard/
  docs/
```

Recommended responsibility:

```text
backend/          Laravel API, database migrations, jobs, services
web/              Customer website UI
mobile/           Flutter customer app
admin-dashboard/  Admin UI or notes if admin stays inside backend
docs/             Project planning and implementation instructions
```

If using Filament, admin dashboard can live inside `backend/` because Filament is a Laravel package. The `admin-dashboard/` folder can keep design specs, route maps, and dashboard documentation.

## API First Rule

All clients should use the same API contract:

```text
Web Client -> Laravel API
Mobile App -> Laravel API
Admin Dashboard -> Laravel API or Laravel internal services
```

Do not duplicate business logic in web/mobile/admin. Business rules stay in backend service classes:

```text
CartService
CheckoutService
StockService
PaymentService
AnalyticsService
```

## Performance Rules

1. Product listing must be paginated.
2. Product listing response must be lightweight.
3. Checkout must use database transaction.
4. Stock update must use row lock.
5. Read-heavy data must use Redis cache.
6. Analytics must use queue jobs and summary tables.

## Shared API Response Format

Success:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {}
}
```

Validation error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Paginated response:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "next_cursor": "cursor-value",
    "per_page": 20
  }
}
```

## Environment Variables

Backend:

```env
APP_NAME=Shop
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=shop
DB_USERNAME=postgres
DB_PASSWORD=password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Clients:

```env
API_BASE_URL=http://localhost:8000/api/v1
```

## Build Order

```text
1. Backend project and PostgreSQL connection
2. Database migrations and seeders
3. Auth API with Sanctum
4. Product catalog API
5. Web product listing/detail
6. Mobile product listing/detail
7. Cart and checkout API
8. Web/mobile cart and checkout
9. Admin product/order management
10. Analytics logs, jobs, summaries
11. Admin analytics dashboard
12. Cache/search optimization
```

