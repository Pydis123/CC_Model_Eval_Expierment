<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Domain\Entity\User;
use App\Http\Middleware\RoleMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class RoleMiddlewareTest extends TestCase
{
    public function testAllowsWhenUserRoleMatches(): void
    {
        $middleware = new RoleMiddleware(['admin']);
        $user = new User(1, 'a@t.local', 'h', 'A', 'admin', null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/admin/users')
            ->withAttribute('user', $user);

        $response = $middleware->process($request, $this->passHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAllowsWhenAnyOfRolesMatch(): void
    {
        $middleware = new RoleMiddleware(['admin', 'agent']);
        $user = new User(1, 'g@t.local', 'h', 'G', 'agent', null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/tickets')
            ->withAttribute('user', $user);

        $response = $middleware->process($request, $this->passHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testReturns403WhenUserRoleDoesNotMatch(): void
    {
        $middleware = new RoleMiddleware(['admin']);
        $user = new User(1, 'r@t.local', 'h', 'R', 'requester', null);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/admin/users')
            ->withAttribute('user', $user);

        $response = $middleware->process($request, $this->passHandler());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testReturns403WhenNoUserAttribute(): void
    {
        $middleware = new RoleMiddleware(['admin']);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/admin/users');

        $response = $middleware->process($request, $this->passHandler());
        $this->assertSame(403, $response->getStatusCode());
    }

    private function passHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };
    }
}
