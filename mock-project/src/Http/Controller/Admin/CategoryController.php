<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin;

use App\Domain\Repository\CategoryRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class CategoryController
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly Twig $twig,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'admin/categories.twig', [
            'categories' => $this->categories->findAll(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'user' => $request->getAttribute('user'),
        ]);
    }
}
