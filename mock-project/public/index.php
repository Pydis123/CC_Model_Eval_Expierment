<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);

if (is_file($rootDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($rootDir)->load();
}

$container = App\App::buildContainer();
$app = App\App::create($container);

App\Http\Routes::register($app);

$app->run();
