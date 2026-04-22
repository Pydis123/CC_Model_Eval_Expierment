<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGetRequestPassesThroughAndEnsuresTokenExists(): void
    {
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');

        $response = $middleware->process($request, $this->passHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertNotEmpty($_SESSION['csrf_token']);
    }

    public function testApiRoutesAreExempt(): void
    {
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/admin/anything');

        $response = $middleware->process($request, $this->passHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostWithoutTokenReturns403(): void
    {
        $_SESSION['csrf_token'] = 'expected-token';

        $middleware = new CsrfMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/tickets')
            ->withParsedBody([]);

        $response = $middleware->process($request, $this->passHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithMatchingTokenPassesThrough(): void
    {
        $_SESSION['csrf_token'] = 'expected-token';

        $middleware = new CsrfMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/tickets')
            ->withParsedBody(['_csrf' => 'expected-token']);

        $response = $middleware->process($request, $this->passHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostWithWrongTokenReturns403(): void
    {
        $_SESSION['csrf_token'] = 'expected-token';

        $middleware = new CsrfMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/tickets')
            ->withParsedBody(['_csrf' => 'wrong']);

        $response = $middleware->process($request, $this->passHandler());

        $this->assertSame(403, $response->getStatusCode());
    }

    private function passHandler(): RequestHandlerInterface
    {
        $response = (new ResponseFactory())->createResponse(200);
        return new class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}
