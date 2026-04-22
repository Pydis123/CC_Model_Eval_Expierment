<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Domain\Repository\UserRepository;
use App\Http\Controller\Admin\UserController;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class UserControllerTest extends IntegrationTestCase
{
    public function testIndexListsAllUsers(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = new UserController(
            new UserRepository($this->pdo),
            $this->createTwig()
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/admin/users'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('admin@test.local', $body);
        $this->assertStringContainsString('agent1@test.local', $body);
    }
}
