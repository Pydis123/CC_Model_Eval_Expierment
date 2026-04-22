<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Domain\Repository\CompanyRepository;
use App\Http\Controller\Admin\CompanyController;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class CompanyControllerTest extends IntegrationTestCase
{
    public function testIndexListsCompanies(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = new CompanyController(
            new CompanyRepository($this->pdo),
            Twig::create(dirname(__DIR__, 4) . '/templates')
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/admin/companies'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Acme AB', (string) $response->getBody());
    }
}
