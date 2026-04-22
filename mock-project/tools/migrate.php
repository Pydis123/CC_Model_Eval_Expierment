<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);

if (is_file($rootDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($rootDir)->load();
}

$config = App\Config::fromArray($_ENV);
$pdo = App\Support\Database::connect($config);
$migrator = new App\Support\Migrator($pdo, $rootDir . '/database/migrations');

$applied = $migrator->run();

if ($applied === []) {
    echo "No pending migrations.\n";
} else {
    echo "Applied " . count($applied) . " migrations:\n";
    foreach ($applied as $filename) {
        echo "  - {$filename}\n";
    }
}
