<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Tests\Support\FixtureLoader;
use App\Tests\Support\SmokeTestCase;

final class LoginFlowSmokeTest extends SmokeTestCase
{
    public function testUnauthenticatedAccessToTicketsRedirectsToLogin(): void
    {
        $response = $this->request('GET', '/tickets');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testLoginPostWithValidCredentialsRedirectsToTickets(): void
    {
        (new FixtureLoader($this->pdo))->seedMinimal();

        $_SESSION['csrf_token'] = 'smoke-token';

        $response = $this->request('POST', '/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
            '_csrf' => 'smoke-token',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/tickets', $response->getHeaderLine('Location'));
    }
}
