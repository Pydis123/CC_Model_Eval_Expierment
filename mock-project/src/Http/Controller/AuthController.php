<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Twig $twig,
    ) {}

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'auth/login.twig', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'error' => null,
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = is_array($body) ? (string) ($body['email'] ?? '') : '';
        $password = is_array($body) ? (string) ($body['password'] ?? '') : '';

        $user = $this->auth->authenticate($email, $password);

        if ($user === null) {
            $response = $response->withStatus(401);
            return $this->twig->render($response, 'auth/login.twig', [
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
                'error' => 'Invalid credentials',
            ]);
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user->id;

        return $response->withStatus(302)->withHeader('Location', '/tickets');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return $response->withStatus(302)->withHeader('Location', '/login');
    }
}
