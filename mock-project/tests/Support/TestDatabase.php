<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use RuntimeException;

final class TestDatabase
{
    private static ?PDO $pdo = null;

    public static function initialize(): void
    {
        $host = (string) ($_ENV['DB_HOST'] ?? '127.0.0.1');
        $port = (string) ($_ENV['DB_PORT'] ?? '3307');
        $user = (string) ($_ENV['DB_USERNAME'] ?? 'ticket_app');
        $pass = (string) ($_ENV['DB_PASSWORD'] ?? 'ticket_app_pw');
        $db   = (string) ($_ENV['TEST_DB_DATABASE'] ?? 'ticket_system_test');

        $rootPdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $rootPdo->exec("DROP DATABASE IF EXISTS `{$db}`");
        $rootPdo->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        self::$pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        self::runMigrations(self::$pdo);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('TestDatabase not initialized');
        }
        return self::$pdo;
    }

    private static function runMigrations(PDO $pdo): void
    {
        $dir = dirname(__DIR__, 2) . '/database/migrations';
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return;
        }
        sort($files);

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }
            $pdo->exec($sql);
        }
    }
}
