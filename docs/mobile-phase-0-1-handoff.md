# E24 Flutter Mobile — Phase 0 / 1 handoff

Reviewed 24 October 2026. Scope: existing mobile foundation and catalog only. Phase 2 has not been started.

## Audit and files

- Read the attached roadmap, root README, mobile/backend/architecture instructions, customer-web handoff and web/AGENTS.md. No root/mobile AGENTS.md or web/.ai/rules exists. The `testing-best-practices` skill referenced in web/AGENTS.md was not installed; regression tests follow existing PHPUnit conventions.
- Installed Flutter 3.47.2, Dart 3.13.2. pubspec.lock: Riverpod 3.4.3, Dio 5.11.1, GoRouter 18.0.2, cached_network_image 4.0.4. No dependency changes. Laravel framework is 13.34.0.
- `mobile/lib/core/`: existing config/client/router/theme reused; typed API errors, reusable retry/image widgets and centralized English copy added. Android main manifest includes Internet permission, debug permits local HTTP; iOS includes local networking declaration and E24 label.
- `mobile/lib/features/products/domain/catalog_models.dart`: typed catalog, variant, option, cursor and filter models; preserves price decimal strings.
- `mobile/lib/features/products/data/catalog_repository.dart`: actual Laravel resource envelopes, shared Dio, cancellable reads, typed errors. HTTP stays outside widgets.
- `mobile/lib/features/products/presentation/`: catalog controller/providers, home and product detail. Filters/search/cursor state stays mounted when pushing detail, with scroll state preserved. Product variant selection uses full server-returned combinations and attributes.
- `web/app/Http/Controllers/Api/V1/ProductController.php`: added JSON filter validation, base-price and availability filters, portable case-insensitive search, stable ID sort tie-breaker, active category/brand filtering and labels. Existing popular sort retained for compatibility.
- `web/app/Http/Resources/ProductListResource.php`: additive boolean `has_available_stock`, calculated by the backend from active non-deleted variant net stock. This avoids using stale product stock_status as the availability authority.
- No admin UI, database, credentials, migrations or stock/customer/order data changed. Live requests are GET only. Test seeders run only in isolated SQLite :memory:.

## Actual catalog contract

GET `/api/v1/categories` → `{data: [{name, slug, children: [...]}]}`.
GET `/api/v1/brands` → `{data: [{name, slug}]}`.
GET `/api/v1/products` → `{data: [...], links: {...}, meta: {next_cursor, prev_cursor, per_page, path}}`.
GET `/api/v1/products/{slug}` → `{data: {images, variants, description, ...}}`.

List query: `search`, `category` slug, `brand` slug, `min_price`, `max_price`, `availability=in_stock|unavailable`, `sort=newest|price_low|price_high|popular`, `limit=1..50`, opaque `cursor`. Mobile uses limit 20 and newest/price sorts. Follow `meta.next_cursor`, never a guessed page number or arbitrary next URL. All query parameters are reapplied with each cursor. Invalid recognized filter input returns JSON 422. Search max 150 chars. IDs deduplicate pages; old results cannot replace a newer filter/search generation.

Price filters sort/filter product base price, not minimum variant price. Category matches direct assignments only. List availability requires an active variant with stock_quantity > reserved_quantity. Detail exposes active variants with `available_stock`, `price`, attribute/value labels and status; an unavailable variant remains inspectable. No inventory mutation occurs. Base and variant prices retain backend decimal strings; display uses the existing dollar convention only.

## Run and limits

See `mobile/README.md` for emulator/device/release commands. Default Android emulator host is 10.0.2.2, iOS simulator defaults to localhost; physical devices need a reachable host. Explicit `API_BASE_URL` overrides either default. HTTPS is required in release; no tokens or provider secrets belong in defines. Image hosts must also be reachable by the device.

English UI only; centralized strings provide a translation foundation, not completed Khmer localization. Cached network images have memory/disk resize bounds; there is no offline catalog database or mutation queue. No featured/related product endpoint is assumed, so home uses actual latest products and detail does not invent related products. No fake ratings, sales, discounts, cart or payment success. Search availability reflects last server read, never a reservation or checkout guarantee.

## Backend gaps and Phase 2 handoff

Verified routes with artisan route:list and inspected controllers/resources/tests. Existing Sanctum API has POST `/register`, POST `/login`, GET `/me`, POST `/logout`. Registration/login returns `{user, token}`; `/me` returns a resource under `data`. There is no refresh-token endpoint, profile PATCH, password change/reset, email verification or avatar endpoint. Do not implement assumed endpoints.

Before Phase 2, audit login and `/me` rejection of inactive/deleted users: User currently does not use SoftDeletes, and `/me` does not itself enforce active status. Add reusable backend protections and regression coverage before claiming secure restoration/revocation. Registration must continue ignoring client role/admin permissions. Plan platform-secure token storage (requires a dependency decision), auth restoration, a bounded 401 handler, route guards, and clearing all account-scoped providers/cache on logout/account switch. Never persist bearer tokens in shared_preferences or log credentials. No automatic refresh loop.

No customer cart/addresses/shipping/coupons/checkout/payment/orders/reviews/wishlist/customer-notification APIs appear in routes. Admin management endpoints are not customer APIs. Tables and InventoryService do not establish customer workflows. Before Phase 3+, implement shared customer ownership checks, guest cart policy, atomic cart/checkout totals, idempotency, stock locks/reservations/snapshots and compatible admin order transitions in web/. Resolve nullable-variant cart uniqueness first. Payment provider/sandbox/deep links/refund policy remain unspecified. Refund approval records are not executed money transfers.

## Verification

- `flutter analyze`: no issues.
- `flutter test`: 8 tests passed. Repository envelope/query/cursor parsing and malformed/422 responses; stale result suppression; pagination deduplication/retry; pending-search invalidation; small-screen large-text retry; variant price/unavailable selection; detail/back preservation without another list request. Mock catalog data is confined to tests.
- `php artisan test --compact tests/Feature/CatalogApiTest.php tests/Feature/StorefrontTest.php tests/Feature/AdminCatalogApiTest.php`: 19 passed, 141 assertions. Isolated SQLite in memory; no production seed or inventory writes. Includes case-insensitive search, combined filters, validation, tied sort cursor traversal and publication/variant visibility.
- Pint formatted the changed PHP paths. Required `--dirty` was attempted but unavailable because this workspace has no Git repository; explicit paths were used instead.
- Android debug APK built successfully; Android runtime/emulator and physical-device behavior not verified. No release signing/build claimed.
- iOS debug simulator build succeeded. Installed/launched on iPhone 18 Pro / iOS 27.0 simulator; catalog home displayed live server products/categories/prices/stock without a mock override. Screenshot: `mobile-verification/ios-catalog.png`. Interaction/state coverage comes from widget tests; manual simulator detail/search/filter interaction and physical iPhone verification are not claimed.
- Live GET `/api/v1/products?limit=1` returned 200 with actual Laravel data/meta cursor envelope. No live mutations performed. Admin publish-to-mobile workflow was not manually exercised; publication visibility has API regression coverage.
- Current local catalog includes a product without an image and seed image URLs returning `image/svg+xml` from placehold.co; the bitmap image widget correctly shows fallback for these. Use reachable PNG/JPEG/WebP catalog uploads for real images; SVG rendering is not included. No seed image/data was changed to manufacture success.
- Network failure/retry and stale requests covered in tests. No packet-throttled real-device performance profile or complete offline integration run claimed. PostgreSQL concurrency, auth and financial flows are outside this phase.

