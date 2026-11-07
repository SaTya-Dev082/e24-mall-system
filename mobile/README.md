# E24 Mobile — Phase 0 / 1

Existing Flutter app extended with real Laravel catalog reads. No dependency added.

## Run

Start the existing backend (no migration or seed required):

```sh
cd /Users/apple/Documents/Me/shop/web
php artisan serve --host=0.0.0.0 --port=8000
```

```sh
cd /Users/apple/Documents/Me/shop/mobile
flutter pub get
# Android emulator (default Android URL)
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
# iOS simulator
flutter run --dart-define=API_BASE_URL=http://localhost:8000/api/v1
# Physical device: replace with the development computer's reachable LAN address
flutter run --dart-define=API_BASE_URL=http://YOUR_LAN_IP:8000/api/v1
```

Physical devices need the same reachable network, firewall access, and any necessary platform development HTTP permission. Android debug permits HTTP; release does not. iOS permits local networking, but device-specific ATS/network policy must be checked for a LAN IP. Prefer HTTPS for device/staging testing. Release requires an explicit HTTPS API URL. Do not embed secrets in dart-defines: app configuration is public.

The backend image URLs must also be reachable by the device. The app preserves absolute API image URLs and resolves relative URLs against the API host. Existing localhost image URLs need backend storage/public URL configuration appropriate to the target device; no environment credentials were changed.

## Implemented

- E24 catalog home with latest products, category chips, search (350 ms debounce), brand/category/base-price/availability filters and newest/price sorting.
- Cursor pagination with request cancellation, stale response guard, duplicate ID prevention, retry and pull-to-refresh.
- Catalog route remains mounted during detail navigation, preserving search, filters, loaded pages and scroll position.
- Detail gallery, plain text description, complete variant combinations with attribute labels, server prices and net stock. Unavailable options can be inspected; no cart operation is offered.
- Loading/empty/error states, cached images with bounded image dimensions and fallback, responsive card columns and centralized copy foundation.
- Riverpod → repository → single Dio client, typed API failures, environment configuration, HTTPS release requirement. No sensitive request logger or token persistence.

Category filtering matches direct assignments, not descendants. Price filtering/sorting uses product base prices, as on the web storefront. Listing availability comes from backend active variant net stock; detail selection shows the server's variant price/stock. Dollar display follows the existing catalog convention; no checkout totals or currency conversion is calculated.

No featured/related/review endpoint is assumed. No ratings/sales/discount claims, cart, authentication UI, checkout or payment success is simulated. Copy is English with a centralized `core/l10n/app_strings.dart` foundation; Khmer translations are not yet shipped. No offline persistence or mutation queue is introduced.

## Checks

```sh
flutter analyze
flutter test
flutter build apk --debug
flutter build ios --simulator --debug
```

See `../docs/mobile-phase-0-1-handoff.md` for verification results, backend contract and Phase 2 gaps.
