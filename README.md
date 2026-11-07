# Shop E-commerce Workspace

This workspace keeps each project separated by client type.

```text
admin-dashboard/  Laravel admin dashboard project
docs/             Architecture, database, and implementation instructions
mobile/           Flutter mobile app project
web/              Laravel customer web project
```

## Stack

```text
Database: PostgreSQL, one shared database named `shop_system`
Cache/Queue/Session: Redis
Web: Laravel, Blade, Tailwind CSS, Alpine.js, Vite
Mobile: Flutter, Riverpod, Dio, GoRouter
Admin: Laravel, Filament-ready, Livewire-ready, Tailwind CSS
```

## Important Docs

```text
docs/database-design.md
docs/system-architecture-instructions.md
docs/web-client-instructions.md
docs/mobile-app-instructions.md
docs/admin-dashboard-instructions.md
docs/backend-api-instructions.md
```

## Current Status

The Flutter mobile project is ready and verified with `flutter analyze` and `flutter test`.

The Laravel `web` and `admin-dashboard` projects are installed and verified. If Composer is slow on a future machine, use a longer process timeout:

```bash
COMPOSER_PROCESS_TIMEOUT=2000 composer install --prefer-dist --no-interaction
```
# e24-mall-system
