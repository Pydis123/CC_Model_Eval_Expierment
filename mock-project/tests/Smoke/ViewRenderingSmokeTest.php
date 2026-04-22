<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Tests\Support\FixtureLoader;
use App\Tests\Support\SmokeTestCase;

final class ViewRenderingSmokeTest extends SmokeTestCase
{
    public function testLoginRendersSwedishByDefault(): void
    {
        $response = $this->request('GET', '/login');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<html lang="sv">', $body);
        $this->assertStringContainsString('Logga in', $body);
    }

    public function testLocaleSwitchToEnglishChangesLoginLabels(): void
    {
        $_SESSION['locale'] = 'en';

        $response = $this->request('GET', '/login');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<html lang="en">', $body);
        $this->assertStringContainsString('Log in', $body);
    }

    public function testLocaleControllerSetsSession(): void
    {
        $response = $this->request('GET', '/locale/en');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('en', $_SESSION['locale']);
    }

    public function testTicketsIndexRendersStatusFilterAlpineAttribute(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['admin']);

        $response = $this->request('GET', '/tickets');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('x-data="{ selected:', $body);
        $this->assertStringContainsString('data-status="open"', $body);
        $this->assertStringContainsString('Printer broken', $body);
    }

    public function testTicketShowRendersStatusBadgeAndConfirmModal(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['agents'][0]);

        $response = $this->request('GET', '/tickets/' . $ids['tickets'][0]);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        // status-badge present
        $this->assertMatchesRegularExpression('/px-2 py-0\.5 text-xs rounded/', $body);
        // confirm-modal present (x-data for confirmOpen)
        $this->assertStringContainsString('confirmOpen', $body);
    }

    public function testAdminUsersRendersWithLayoutNav(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['admin']);

        $response = $this->request('GET', '/admin/users');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Användare', $body);
        $this->assertStringContainsString('admin@test.local', $body);
        // Layout title renders t('app.name')
        $this->assertStringContainsString('Ärendesystem', $body);
    }
}
