<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->ensureTokenExists();

        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        if (!in_array($request->getMethod(), self::MUTATING_METHODS, true)) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $submitted = is_array($body) ? ($body['_csrf'] ?? null) : null;
        $expected = $_SESSION['csrf_token'] ?? null;

        if (!is_string($submitted) || !is_string($expected) || !hash_equals($expected, $submitted)) {
            $response = (new ResponseFactory())->createResponse(403);
            $response->getBody()->write('CSRF token mismatch');
            return $response;
        }

        return $handler->handle($request);
    }

    private function ensureTokenExists(): void
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
