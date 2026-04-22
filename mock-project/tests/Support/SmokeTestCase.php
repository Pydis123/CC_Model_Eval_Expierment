<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\App;
use App\Http\Routes;
use Psr\Http\Message\ResponseInterface;
use Slim\App as SlimApp;
use Slim\Psr7\Factory\ServerRequestFactory;

abstract class SmokeTestCase extends IntegrationTestCase
{
    protected SlimApp $app;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];

        $container = App::buildContainer($this->pdo);
        $this->app = App::create($container, $this->pdo);
        Routes::register($this->app);
    }

    /**
     * @param array<string,mixed>|null $parsedBody
     */
    protected function request(string $method, string $path, ?array $parsedBody = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }
        return $this->app->handle($request);
    }

    protected function loginAs(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = 'test-token';
    }

    protected function csrf(): string
    {
        return $_SESSION['csrf_token'] ?? 'test-token';
    }
}
