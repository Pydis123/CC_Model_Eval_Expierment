<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Probe;

use PDO;
use RuntimeException;

class QueryCountProbe
{
    /**
     * Boots the mock-project Slim app from the given worktree, resets + seeds
     * the test DB, dispatches a GET request, and returns the number of SQL
     * queries observed on the PDO connection during handling.
     */
    public function count(string $worktreePath, string $route, bool $authAsAdmin = false): int
    {
        $mockProject = $worktreePath . '/mock-project';
        if (!is_file($mockProject . '/vendor/autoload.php')) {
            throw new RuntimeException("mock-project vendor missing at {$mockProject}");
        }

        require_once $mockProject . '/vendor/autoload.php';

        if (is_file($mockProject . '/.env')) {
            \Dotenv\Dotenv::createImmutable($mockProject)->load();
        } else {
            $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $_ENV['DB_PORT'] = $_ENV['DB_PORT'] ?? '3307';
            $_ENV['DB_USERNAME'] = $_ENV['DB_USERNAME'] ?? 'ticket_app';
            $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? 'ticket_app_pw';
            $_ENV['TEST_DB_DATABASE'] = $_ENV['TEST_DB_DATABASE'] ?? 'ticket_system_test';
        }

        \App\Tests\Support\TestDatabase::initialize();
        $pdo = \App\Tests\Support\TestDatabase::pdo();

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $fixtures = new \App\Tests\Support\FixtureLoader($pdo);
        $ids = $fixtures->seedMinimal();

        $_SESSION = [];
        if ($authAsAdmin) {
            $_SESSION['user_id'] = $ids['admin'];
            $_SESSION['csrf_token'] = 'probe-token';
        }

        $container = \App\App::buildContainer($pdo);
        $app = \App\App::create($container, $pdo);
        \App\Http\Routes::register($app);

        $before = (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];

        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('GET', $route);
        $app->handle($request);

        $after = (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];

        return $after - $before - 1;
    }
}
