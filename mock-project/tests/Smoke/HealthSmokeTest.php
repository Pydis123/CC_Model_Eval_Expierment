<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Tests\Support\SmokeTestCase;

final class HealthSmokeTest extends SmokeTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $response = $this->request('GET', '/api/health');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', (string) $response->getBody());
    }
}
