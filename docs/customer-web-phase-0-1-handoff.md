# E24 Customer Web — Phase 0/1 audit and handoff

Reviewed 24 October 2026. Scope: customer foundation and catalog only.

## Existing implementation

- `web/` owns Laravel 13.34.0 backend, migrations, API and Blade storefront. Vite is 8.3.2 in the installed build; package.json declares Vite ^8, Tailwind ^4. No dependency added.
- Catalog models support categories, brands, products, images, variants and attribute values. Product, variant, category and brand queries exclude soft deletes through Eloquent. Storefront products require `status=active`; out-of-stock published products remain browsable. Draft/inactive/deleted products return 404.
- Category/brand navigation only exposes active non-deleted records. Parent category filters match direct product assignments, not descendants. An inactive category/brand does not independently unpublish an active product; its labels are omitted on detail. Product publication remains the visibility authority.
- Existing API authentication issues Sanctum tokens. Browser account authentication, addresses UI, password reset and verification are not implemented. User does not yet implement MustVerifyEmail or SoftDeletes, despite deleted_at being used by admin checks. Phase 2 must explicitly reject deleted users and prevent role assignment from customer input.
- Address/cart tables exist; tables alone do not provide working customer workflows. Cart nullable variant unique index allows duplicate NULL-variant lines in PostgreSQL. Resolve this before Phase 3.
- InventoryService reserves net stock, uses transaction locks and idempotency references, and supports commit/release/expiry/returns. Catalog reads do not reserve stock.
- Admin order transition locks the order and checks payment/COD eligibility. Confirmation reuses linked reservations or creates missing reservations, verifies quantity and commits. Cancellation releases pending reservations or returns committed quantities; payment refunds remain separate. Status history is recorded. Future checkout must create linked reservations atomically and reuse these semantics, with consistent product/variant lock ordering.
- Refund request/approval records exist; approval is not provider refund execution. Shipping method/service-area configuration exists; no customer eligibility/rate calculator or carrier integration is claimed.

## Catalog implementation

- Routes: GET `/`, GET `/products`, GET `/products/{slug}`. Existing `/api/v1` routes are preserved. Storefront route names use a separate namespace from admin API resource names.
- Reusable layout and product cards; image gallery, broken-image fallback, explicit stock/variant status, related products, empty/422/404/500 views.
- Mobile category toggle uses aria-expanded/aria-controls, Escape recovery and a no-JavaScript navigation fallback. Skip link and visible keyboard focus are provided.
- URL filters: search, category, brand, min_price, max_price, availability (`in_stock`, `unavailable`), sort (`newest`, `price_low`, `price_high`), page. Fixed 12-item pages; invalid recognized parameters return 422 with recovery link. Unknown parameters are not used or retained in pagination.
- Availability means an active non-deleted variant has stock_quantity > reserved_quantity. Products without such variants are unavailable, including products without inventory variants. Preorder is a display status, not an ordering promise.
- Listing selects lightweight fields, eager-loads primary images and uses a variant existence subquery. Every sort has unique ID tie-breaker. Detail eager-loads variant attributes/values.
- Blade escapes descriptions and labels; descriptions remain plain text. No fake reviews/discounts/sales. No cart controls until Phase 3.
- StorefrontFormat renders existing dollar-denominated catalog decimals without float conversion. This preserves the pre-existing display convention; it is not a checkout currency/tax policy. Date helper formats Asia/Phnom_Penh as `d M Y, H:i`. No customer date workflow is implemented yet.
- Effective web session cookie is `shop-web-session`; admin is `shop_admin_session`. Configurations and credentials remain unchanged. Ports do not isolate cookies; retain distinct cookie names when deploying. Phase 2 Blade POST forms must use @csrf and browser sessions; do not reuse token API login for browser authentication. Review the shared default XSRF-TOKEN cookie if adding JavaScript mutation clients across localhost ports.
- No public response caching introduced: catalog changes appear on subsequent requests without stale publication/stock caches. Add caching only with shared admin invalidation.

## Verification and run

```bash
cd /Users/apple/Documents/Me/shop/web
php artisan serve --port=8000
npm run dev
# Production assets
npm run build
# Isolated in-memory SQLite tests (phpunit.xml)
php artisan test --compact
```

37 tests passed, 1 PostgreSQL concurrency test skipped, 218 assertions. Frontend build passed; optional fontaine fallback warning remains. Pint formatted changed PHP files explicitly because --dirty requires Git and this workspace is not a Git repository. No project database migration, seeding or stock write was performed.

Browser provider inventory is empty in this session. Desktop/mobile visual layout, keyboard interaction, gallery and live variant JavaScript need browser review on the user's machine. Responsive CSS and rendered markup are implemented; automated HTTP tests do not establish visual correctness. PostgreSQL concurrency coverage is not claimed from the SQLite run. Payment, checkout and account flows remain out of scope.

## Phase 2 handoff

Implement customer session registration/login/logout, session regeneration, throttling, active/non-deleted customer guards and strict validated fields. Add password reset/email verification with local mail testing and safe profile/password update. Implement owned address CRUD/default selection with transactions for default changes. Test cross-customer access, blocked/deleted accounts, role escalation, session fixation and CSRF. Reuse existing users/addresses schema after reviewing constraints; only web owns required migrations. Do not implement cart/checkout automatically. Set mail provider and currency/payment/tax policies before dependent later phases.
