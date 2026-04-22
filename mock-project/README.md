# Ticket System

A small support-ticket application built with Slim 4, PHP-DI, Twig, Alpine.js and MariaDB.

## Features

- Ticket lifecycle (new → open → pending → resolved → closed)
- Role-based access (admin, agent, requester)
- Commenting on tickets
- Category + company organisation

## Quick start

```bash
docker compose up -d
composer install
cp .env.example .env
php tools/migrate.php
php tools/seed_demo.php
php -S localhost:8080 -t public
```

Visit http://localhost:8080 and log in with `admin@demo.local` / `password`.

## Tests

```bash
composer test
```

Requires the MariaDB container to be running.

## License

MIT (see LICENSE).
