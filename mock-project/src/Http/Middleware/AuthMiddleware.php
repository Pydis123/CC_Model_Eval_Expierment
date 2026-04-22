<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly UserRepository $users) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $_SESSION['user_id'] ?? null;
        $user = is_int($userId) ? $this->users->findById($userId) : null;

        if ($user === null) {
            return $this->unauthorized($request);
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }

    private function unauthorized(ServerRequestInterface $request): ResponseInterface
    {
        $factory = new ResponseFactory();
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, '/api/')) {
            $response = $factory->createResponse(401);
            $response->getBody()->write('{"error":"unauthorized"}');
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $factory->createResponse(302)->withHeader('Location', '/login');
    }
}
