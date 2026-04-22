<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\Category;
use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class TicketRepositoryTest extends TestCase
{
    private PDO $pdo;
    private TicketRepository $repo;
    private int $requesterId;
    private int $categoryId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->repo = new TicketRepository($this->pdo);

        $userRepo = new UserRepository($this->pdo);
        $catRepo = new CategoryRepository($this->pdo);
        $this->requesterId = (int) $userRepo->insert(
            new User(null, 'r@t.local', 'h', 'R', 'requester', null)
        )->id;
        $this->categoryId = (int) $catRepo->insert(
            new Category(null, 'Technical', 24)
        )->id;
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testInsertAndFindById(): void
    {
        $saved = $this->repo->insert(new Ticket(
            null, 'Printer broken', 'Details', 'new', null, $this->requesterId, $this->categoryId
        ));

        $this->assertNotNull($saved->id);
        $found = $this->repo->findById((int) $saved->id);
        $this->assertNotNull($found);
        $this->assertSame('Printer broken', $found->subject);
    }

    public function testFindAllReturnsRawRowsOrderedById(): void
    {
        $this->repo->insert(new Ticket(null, 'a', 'd', 'new', null, $this->requesterId, $this->categoryId));
        $this->repo->insert(new Ticket(null, 'b', 'd', 'new', null, $this->requesterId, $this->categoryId));

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
        $this->assertSame('a', $all[0]->subject);
    }

    public function testUpdateStatus(): void
    {
        $t = $this->repo->insert(new Ticket(null, 's', 'd', 'new', null, $this->requesterId, $this->categoryId));

        $this->repo->updateStatus((int) $t->id, 'open');

        $found = $this->repo->findById((int) $t->id);
        $this->assertSame('open', $found->status);
    }

    public function testFindByStatus(): void
    {
        $this->repo->insert(new Ticket(null, 'a', 'd', 'new', null, $this->requesterId, $this->categoryId));
        $t = $this->repo->insert(new Ticket(null, 'b', 'd', 'new', null, $this->requesterId, $this->categoryId));
        $this->repo->updateStatus((int) $t->id, 'open');

        $open = $this->repo->findByStatus('open');
        $this->assertCount(1, $open);
        $this->assertSame('b', $open[0]->subject);
    }

    public function testFindAllDoesNotJoinRelatedTables(): void
    {
        // Guard: findAll returns ticket columns only; callers load related
        // entities themselves. Adding JOINs here would be a design change.
        $this->repo->insert(new Ticket(null, 'x', 'd', 'new', null, $this->requesterId, $this->categoryId));

        $reflection = new \ReflectionClass(TicketRepository::class);
        $method = $reflection->getMethod('findAll');
        $source = file_get_contents($method->getFileName());
        $this->assertStringNotContainsString('JOIN', strtoupper($source ?: ''), 'findAll must NOT use JOINs');
    }
}
