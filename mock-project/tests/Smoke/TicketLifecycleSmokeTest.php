<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Tests\Support\FixtureLoader;
use App\Tests\Support\SmokeTestCase;

final class TicketLifecycleSmokeTest extends SmokeTestCase
{
    public function testAuthenticatedUserCanListTickets(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['admin']);

        $response = $this->request('GET', '/tickets');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Printer broken', (string) $response->getBody());
    }

    public function testAgentCanTransitionTicketFromOpenToPending(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['agents'][0]);

        $ticketId = $ids['tickets'][0]; // seeded with status 'open'
        $response = $this->request('POST', "/tickets/{$ticketId}/status", [
            'status' => 'pending',
            '_csrf' => $this->csrf(),
        ]);

        $this->assertSame(302, $response->getStatusCode());

        $status = $this->pdo->query("SELECT status FROM tickets WHERE id = {$ticketId}")->fetchColumn();
        $this->assertSame('pending', $status);
    }

    public function testRequesterCannotAccessAdminRoutes(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();
        $this->loginAs($ids['requesters'][0]);

        $response = $this->request('GET', '/admin/users');
        $this->assertSame(403, $response->getStatusCode());
    }
}
