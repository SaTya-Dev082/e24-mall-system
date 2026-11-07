# Mobile App Instructions

Mobile App គឺ Flutter customer app សម្រាប់ browse products, cart, checkout, profile, wishlist, និង order tracking។

## Technology

```text
Flutter
Dart
Riverpod
Dio
GoRouter
Freezed/json_serializable optional
Hive or SharedPreferences
```

Recommended choice: Riverpod because state management is clean, testable, and easy to maintain as features grow.

## Mobile Folder Structure

```text
mobile/
  lib/
    main.dart
    app.dart
    core/
      api/
        api_client.dart
        api_exception.dart
      config/
        app_config.dart
      router/
        app_router.dart
      storage/
        token_storage.dart
      theme/
        app_theme.dart
      widgets/
    features/
      auth/
        data/
        domain/
        presentation/
      products/
        data/
        domain/
        presentation/
      cart/
        data/
        domain/
        presentation/
      checkout/
        data/
        domain/
        presentation/
      orders/
        data/
        domain/
        presentation/
      profile/
        data/
        domain/
        presentation/
      wishlist/
        data/
        domain/
        presentation/
```

## API Client Rule

Use one Dio client:

```text
baseUrl from config
auth token interceptor
error interceptor
retry only where safe
request timeout
response parsing
```

Do not call HTTP directly from UI widgets. Use repository classes.

Flow:

```text
Screen -> Provider/Controller -> Repository -> ApiClient -> Laravel API
```

## Main Screens

```text
SplashScreen
LoginScreen
RegisterScreen
HomeScreen
ProductListScreen
ProductDetailScreen
CartScreen
CheckoutScreen
OrderSuccessScreen
OrdersScreen
OrderDetailScreen
ProfileScreen
AddressListScreen
WishlistScreen
```

## State Management

Recommended providers:

```text
authProvider
productListProvider
productDetailProvider
cartProvider
checkoutProvider
ordersProvider
profileProvider
wishlistProvider
```

Use pagination state for product list:

```text
items
isLoading
isLoadingMore
nextCursor
error
filters
```

## Mobile Performance Rules

```text
Use cursor pagination for products.
Use cached network images.
Keep API response small.
Store auth token securely.
Do not load full product detail in list.
Show skeleton loading for product cards.
Handle offline/network errors gracefully.
```

## Local Storage

Use local storage for:

```text
auth token
basic user profile cache
recent searches
selected currency/language
cart fallback only if guest flow requires it
```

Backend remains the source of truth for cart and orders.

## User Flow

Browse:

```text
Home -> Product List -> Product Detail -> Add to Cart
```

Checkout:

```text
Cart -> Address -> Shipping -> Payment -> Success
```

Tracking:

```text
Orders -> Order Detail -> Shipment Status -> Review Product
```

## Error Handling

Handle these cases in UI:

```text
401 unauthenticated -> go to login
403 forbidden -> show permission message
422 validation -> show form errors
409 conflict -> stock/price changed message
500 server -> retry message
network timeout -> retry
```

