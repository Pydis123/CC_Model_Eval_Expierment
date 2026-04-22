<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Http\Controller\LocaleController;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LocaleControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testValidLocaleSetsSessionAndRedirectsToReferer(): void
    {
        $controller = new LocaleController();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/locale/en')
            ->withHeader('Referer', '/tickets');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->set($request, $response, ['code' => 'en']);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/tickets', $result->getHeaderLine('Location'));
        $this->assertSame('en', $_SESSION['locale']);
    }

    public function testValidLocaleRedirectsToTicketsWhenNoReferer(): void
    {
        $controller = new LocaleController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/locale/sv');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->set($request, $response, ['code' => 'sv']);

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/tickets', $result->getHeaderLine('Location'));
        $this->assertSame('sv', $_SESSION['locale']);
    }

    public function testUnknownLocaleReturns404(): void
    {
        $controller = new LocaleController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/locale/de');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->set($request, $response, ['code' => 'de']);

        $this->assertSame(404, $result->getStatusCode());
        $this->assertArrayNotHasKey('locale', $_SESSION);
    }
}
