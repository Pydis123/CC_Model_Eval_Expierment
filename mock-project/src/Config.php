<?php

declare(strict_types=1);

namespace App;

final class Config
{
    public function __construct(
        public readonly string $appEnv,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbDatabase,
        public readonly string $dbUsername,
        public readonly string $dbPassword,
    ) {}

    /**
     * @param array<string, string|int|null> $env
     */
    public static function fromArray(array $env): self
    {
        return new self(
            appEnv: (string) ($env['APP_ENV'] ?? 'development'),
            dbHost: (string) ($env['DB_HOST'] ?? '127.0.0.1'),
            dbPort: (int) ($env['DB_PORT'] ?? 3307),
            dbDatabase: (string) ($env['DB_DATABASE'] ?? 'ticket_system'),
            dbUsername: (string) ($env['DB_USERNAME'] ?? 'ticket_app'),
            dbPassword: (string) ($env['DB_PASSWORD'] ?? 'ticket_app_pw'),
        );
    }
}
