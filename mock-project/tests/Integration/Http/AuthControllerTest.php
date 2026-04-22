<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Entity\User;
use App\Domain\I18n\I18nLoader;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\AuthService;
use App\Domain\Service\PasswordHasher;
use App\Http\Controller\AuthController;
use App\Http\I18nExtension;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;

final class AuthControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testLoginWithValidCredentialsSetsSessionAndRedirects(): void
    {
        $users = new UserRepository($this->pdo);
        $hasher = new PasswordHasher();
        $users->insert(new User(null, 'a@t.local', $hasher->hash('secret'), 'A', 'admin', null));

        $controller = new AuthController(
            new AuthService($users, $hasher),
            $this->createTwig()
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/login')
            ->withParsedBody(['email' => 'a@t.local', 'password' => 'secret']);
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->login($request, $response);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/tickets', $result->getHeaderLine('Location'));
        $this->assertArrayHasKey('user_id', $_SESSION);
    }

    public function testLoginWithInvalidCredentialsReturnsToLoginWithError(): void
    {
        $users = new UserRepository($this->pdo);
        $hasher = new PasswordHasher();
        $users->insert(new User(null, 'a@t.local', $hasher->hash('secret'), 'A', 'admin', null));

        $controller = new AuthController(
            new AuthService($users, $hasher),
            $this->createTwig()
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/login')
            ->withParsedBody(['email' => 'a@t.local', 'password' => 'wrong']);
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->login($request, $response);

        $this->assertSame(401, $result->getStatusCode());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLogoutClearsSession(): void
    {
        $_SESSION = ['user_id' => 1, 'other' => 'x'];

        $users = new UserRepository($this->pdo);
        $controller = new AuthController(
            new AuthService($users, new PasswordHasher()),
            $this->createTwig()
        );

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/logout');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->logout($request, $response);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/login', $result->getHeaderLine('Location'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginViewRendersSwedishLabels(): void
    {
        $twig = Twig::create(dirname(__DIR__, 3) . '/templates');
        $strings = (new I18nLoader($this->pdo))->forLocale('sv');
        $twig->addExtension(new I18nExtension($strings));
        $twig->getEnvironment()->addGlobal('locale', 'sv');

        $users = new UserRepository($this->pdo);
        $hasher = new PasswordHasher();
        $controller = new AuthController(new AuthService($users, $hasher), $twig);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/login');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->showLogin($request, $response);
        $body = (string) $result->getBody();

        $this->assertStringContainsString('Logga in', $body);
        $this->assertStringContainsString('E-postadress', $body);
        $this->assertStringContainsString('Lösenord', $body);
    }
}
