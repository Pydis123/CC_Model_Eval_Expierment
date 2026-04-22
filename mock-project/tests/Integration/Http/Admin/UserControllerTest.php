<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Admin;

use App\Domain\I18n\I18nLoader;
use App\Domain\Repository\UserRepository;
use App\Http\Controller\Admin\UserController;
use App\Http\I18nExtension;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class UserControllerTest extends IntegrationTestCase
{
    public function testIndexListsAllUsers(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $controller = new UserController(
            new UserRepository($this->pdo),
            $this->createTwig()
        );

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/admin/users'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('admin@test.local', $body);
        $this->assertStringContainsString('agent1@test.local', $body);
    }

    public function testIndexRendersSwedishLabels(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $_SESSION['locale'] = 'sv';
        $loader = new I18nLoader($this->pdo);
        $twig = Twig::create(dirname(__DIR__, 4) . '/templates');
        $twig->addExtension(new I18nExtension(fn(string $l) => $loader->forLocale($l)));

        $controller = new UserController(new UserRepository($this->pdo), $twig);

        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/admin/users'),
            (new ResponseFactory())->createResponse()
        );

        $body = (string) $response->getBody();
        $this->assertStringContainsString('Användare', $body);
        $this->assertStringContainsString('E-post', $body);
        $this->assertStringContainsString('Roll', $body);
    }
}
