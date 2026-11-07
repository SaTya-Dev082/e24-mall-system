# Mobile App Project

Flutter customer mobile app.

## Purpose

```text
Authentication
Product catalog
Product detail
Cart
Checkout
Order tracking
Profile
Wishlist
Reviews
```

## Stack

```text
Flutter
Dart
Riverpod
Dio
GoRouter
SharedPreferences
CachedNetworkImage
```

## Setup

```bash
flutter pub get
```

## Run

```bash
flutter run --dart-define=API_BASE_URL=http://localhost:8000/api/v1
```

For Android emulator, use host machine URL if needed:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## Verify

```bash
flutter analyze
flutter test
```

