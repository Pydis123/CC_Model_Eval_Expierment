# Ticket System

Support ticket application.

## Stack

PHP 8.4, Slim 4, PHP-DI, Twig, Alpine.js, MariaDB 10.11, PHPUnit 11.

## Running locally

```bash
# Start database (from repository root)
docker compose up -d

# Install dependencies
composer install

# Copy env file
cp .env.example .env

# Run migrations
php tools/migrate.php

# Seed demo data (optional)
php tools/seed_demo.php

# Start dev server
php -S localhost:8080 -t public
```

## Testing

- Run all tests: `composer test`
- Run unit suite only: `./vendor/bin/phpunit --testsuite Unit`
- Run static analysis: `composer stan`

## Conventions

- `declare(strict_types=1)` in every PHP file
- English in code; user-facing strings via i18n when UI is added
- PascalCase classes, camelCase methods, snake_case tables
- PSR-12 formatting
- Write tests alongside features; integration tests hit a real MariaDB

## Project layout

- `public/index.php` — entrypoint
- `src/Http/Controller/` — thin controllers
- `src/Http/Middleware/` — session, auth, RBAC, CSRF
- `src/Domain/Entity/` — plain value objects
- `src/Domain/Repository/` — PDO data access
- `src/Domain/Service/` — business logic
- `src/Support/` — infrastructure (PDO factory, migrator)
- `templates/` — Twig templates
- `database/migrations/` — numerically prefixed SQL files
- `tools/` — CLI scripts (migrate, seed)
- `tests/` — PHPUnit suites (Unit, Integration, Smoke)
