<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Config;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testBuildsPdoInstance(): void
    {
        $config = Config::fromArray([
            'DB_HOST' => (string) $_ENV['DB_HOST'],
            'DB_PORT' => (string) $_ENV['DB_PORT'],
            'DB_DATABASE' => (string) $_ENV['TEST_DB_DATABASE'],
            'DB_USERNAME' => (string) $_ENV['DB_USERNAME'],
            'DB_PASSWORD' => (string) $_ENV['DB_PASSWORD'],
        ]);

        $pdo = Database::connect($config);

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testPdoIsConfiguredToThrowOnError(): void
    {
        $config = Config::fromArray([
            'DB_HOST' => (string) $_ENV['DB_HOST'],
            'DB_PORT' => (string) $_ENV['DB_PORT'],
            'DB_DATABASE' => (string) $_ENV['TEST_DB_DATABASE'],
            'DB_USERNAME' => (string) $_ENV['DB_USERNAME'],
            'DB_PASSWORD' => (string) $_ENV['DB_PASSWORD'],
        ]);

        $pdo = Database::connect($config);

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testCanExecuteSimpleQuery(): void
    {
        $config = Config::fromArray([
            'DB_HOST' => (string) $_ENV['DB_HOST'],
            'DB_PORT' => (string) $_ENV['DB_PORT'],
            'DB_DATABASE' => (string) $_ENV['TEST_DB_DATABASE'],
            'DB_USERNAME' => (string) $_ENV['DB_USERNAME'],
            'DB_PASSWORD' => (string) $_ENV['DB_PASSWORD'],
        ]);

        $pdo = Database::connect($config);
        $row = $pdo->query('SELECT 1 AS n')->fetch();

        $this->assertSame(1, (int) $row['n']);
    }
}
