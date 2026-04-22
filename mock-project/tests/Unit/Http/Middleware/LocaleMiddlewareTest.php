<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Middleware;

use App\Http\Middleware\LocaleMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LocaleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testDefaultsToSvWhenSessionMissing(): void
    {
        $captured = null;
        $handler = $this->capturingHandler($captured);

        $middleware = new LocaleMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');

        $middleware->process($request, $handler);

        $this->assertSame('sv', $captured);
        $this->assertSame('sv', $_SESSION['locale']);
    }

    public function testUsesSessionLocaleWhenSupported(): void
    {
        $_SESSION['locale'] = 'en';

        $captured = null;
        $handler = $this->capturingHandler($captured);

        $middleware = new LocaleMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');

        $middleware->process($request, $handler);

        $this->assertSame('en', $captured);
        $this->assertSame('en', $_SESSION['locale']);
    }

    public function testFallsBackToSvForUnsupportedLocale(): void
    {
        $_SESSION['locale'] = 'de';

        $captured = null;
        $handler = $this->capturingHandler($captured);

        $middleware = new LocaleMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/tickets');

        $middleware->process($request, $handler);

        $this->assertSame('sv', $captured);
        $this->assertSame('sv', $_SESSION['locale']);
    }

    private function capturingHandler(mixed &$captured): RequestHandlerInterface
    {
        return new class($captured) implements RequestHandlerInterface {
            public function __construct(public mixed &$captured) {}
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getAttribute('locale');
                return (new ResponseFactory())->createResponse(200);
            }
        };
    }
}
