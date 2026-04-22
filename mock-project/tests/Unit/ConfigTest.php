<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testLoadsDatabaseConfigFromEnvArray(): void
    {
        $config = Config::fromArray([
            'DB_HOST' => 'db.local',
            'DB_PORT' => '3310',
            'DB_DATABASE' => 'tickets',
            'DB_USERNAME' => 'app',
            'DB_PASSWORD' => 'secret',
        ]);

        $this->assertSame('db.local', $config->dbHost);
        $this->assertSame(3310, $config->dbPort);
        $this->assertSame('tickets', $config->dbDatabase);
        $this->assertSame('app', $config->dbUsername);
        $this->assertSame('secret', $config->dbPassword);
    }

    public function testDefaultsWhenKeysMissing(): void
    {
        $config = Config::fromArray([]);

        $this->assertSame('127.0.0.1', $config->dbHost);
        $this->assertSame(3307, $config->dbPort);
        $this->assertSame('ticket_system', $config->dbDatabase);
    }

    public function testAppEnvDefaultsToDevelopment(): void
    {
        $config = Config::fromArray([]);

        $this->assertSame('development', $config->appEnv);
    }

    public function testReadsAppEnvWhenProvided(): void
    {
        $config = Config::fromArray(['APP_ENV' => 'production']);

        $this->assertSame('production', $config->appEnv);
    }
}
