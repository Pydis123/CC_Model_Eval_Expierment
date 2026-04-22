<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin;

use App\Domain\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Twig $twig,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'admin/users.twig', [
            'users' => $this->users->findAll(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }
}
