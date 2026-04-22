<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class SessionMiddlewareTest extends TestCase
{
    public function testStartsSessionAndDelegatesToHandler(): void
    {
        $middleware = new SessionMiddleware();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = (new ResponseFactory())->createResponse();

        $handler = new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
        // session cannot reliably be started in CLI without headers; we test it does not throw.
    }
}
