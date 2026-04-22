<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);

if (is_file($rootDir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($rootDir);
    $dotenv->load();
} else {
    $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3307';
    $_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'ticket_app';
    $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'ticket_app_pw';
    $_ENV['TEST_DB_DATABASE'] = $_ENV['TEST_DB_DATABASE'] ?? 'ticket_system_test';
}

App\Tests\Support\TestDatabase::initialize();
