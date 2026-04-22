<?php

declare(strict_types=1);

namespace App\Http\Controller\Admin;

use App\Domain\Repository\CompanyRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

final class CompanyController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly Twig $twig,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->twig->render($response, 'admin/companies.twig', [
            'companies' => $this->companies->findAll(),
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }
}
