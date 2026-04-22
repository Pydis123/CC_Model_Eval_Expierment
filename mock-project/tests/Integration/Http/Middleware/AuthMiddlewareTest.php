<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Middleware;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Http\Middleware\AuthMiddleware;
use App\Tests\Support\IntegrationTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class AuthMiddlewareTest extends IntegrationTestCase
{
    public function testAttachesUserWhenSessionHasUserId(): void
    {
        $users = new UserRepository($this->pdo);
        $user = $users->insert(new User(null, 'u@t.local', 'h', 'U', 'agent', null));

        $_SESSION = ['user_id' => $user->id];

        $captured = null;
        $middleware = new AuthMiddleware($users);
        $handler = new class($captured) implements RequestHandlerInterface {
            public function __construct(public mixed &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute('user');
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');
        $middleware->process($request, $handler);

        $this->assertInstanceOf(User::class, $handler->captured);
        $this->assertSame('u@t.local', $handler->captured->email);
    }

    public function testRedirectsToLoginForHtmlRouteWhenNotAuthenticated(): void
    {
        $_SESSION = [];
        $users = new UserRepository($this->pdo);
        $middleware = new AuthMiddleware($users);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');
        $response = $middleware->process($request, $this->handler200());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testReturns401ForApiRouteWhenNotAuthenticated(): void
    {
        $_SESSION = [];
        $users = new UserRepository($this->pdo);
        $middleware = new AuthMiddleware($users);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/foo');
        $response = $middleware->process($request, $this->handler200());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRedirectsIfSessionUserIdRefersToMissingUser(): void
    {
        $_SESSION = ['user_id' => 99999];
        $users = new UserRepository($this->pdo);
        $middleware = new AuthMiddleware($users);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');
        $response = $middleware->process($request, $this->handler200());

        $this->assertSame(302, $response->getStatusCode());
    }

    private function handler200(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };
    }
}
