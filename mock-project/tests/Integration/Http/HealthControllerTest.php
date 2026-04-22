<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Http\Controller\HealthController;
use App\Tests\Support\IntegrationTestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthControllerTest extends IntegrationTestCase
{
    public function testReturnsOkJson(): void
    {
        $controller = new HealthController();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = (new ResponseFactory())->createResponse();

        $result = $controller->health($request, $response);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
        $this->assertSame('{"status":"ok"}', (string) $result->getBody());
    }
}
