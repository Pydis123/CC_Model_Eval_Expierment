<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Domain\Entity\Ticket;
use App\Domain\Repository\TicketRepository;
use App\Domain\Service\RecentActivityService;
use App\Tests\Support\FixtureLoader;
use App\Tests\Support\IntegrationTestCase;

final class RecentActivityServiceTest extends IntegrationTestCase
{
    public function testCountsJustCreatedTicket(): void
    {
        $ids = (new FixtureLoader($this->pdo))->seedMinimal();

        (new TicketRepository($this->pdo))->insert(new Ticket(
            null, 'Fresh', 'Just now', 'new', null,
            $ids['requesters'][0], $ids['categories'][0]
        ));

        $service = new RecentActivityService($this->pdo);

        $this->assertSame(1, $service->countRecentTickets());
    }
}
