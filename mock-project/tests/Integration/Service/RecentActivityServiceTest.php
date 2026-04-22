<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Domain\Entity\Category;
use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\RecentActivityService;
use App\Tests\Support\IntegrationTestCase;

final class RecentActivityServiceTest extends IntegrationTestCase
{
    public function testCountsJustCreatedTicket(): void
    {
        $user = (new UserRepository($this->pdo))->insert(
            new User(null, 'r@t.local', 'h', 'R', 'requester', null)
        );
        $category = (new CategoryRepository($this->pdo))->insert(
            new Category(null, 'Technical', 24)
        );

        (new TicketRepository($this->pdo))->insert(new Ticket(
            null, 'Fresh', 'Just now', 'new', null,
            (int) $user->id, (int) $category->id
        ));

        $service = new RecentActivityService($this->pdo);

        $this->assertSame(1, $service->countRecentTickets());
    }
}
