<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Domain\Repository\CategoryRepository;
use App\Http\Controller\Admin\CategoryController;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class CategoryControllerTest extends IntegrationTestCase
{
    public function testIndexListsCategories(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = new CategoryController(
            new CategoryRepository($this->pdo),
            Twig::create(dirname(__DIR__, 4) . '/templates')
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/admin/categories'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Technical', (string) $response->getBody());
    }
}
