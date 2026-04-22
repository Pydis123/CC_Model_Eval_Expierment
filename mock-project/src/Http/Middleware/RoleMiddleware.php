<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Entity\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(private readonly array $allowedRoles) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User || !in_array($user->role, $this->allowedRoles, true)) {
            $response = (new ResponseFactory())->createResponse(403);
            $response->getBody()->write('Forbidden');
            return $response;
        }

        return $handler->handle($request);
    }
}
