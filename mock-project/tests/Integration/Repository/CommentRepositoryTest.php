<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\Category;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Ticket;
use App\Domain\Entity\User;
use App\Domain\Repository\CategoryRepository;
use App\Domain\Repository\CommentRepository;
use App\Domain\Repository\TicketRepository;
use App\Domain\Repository\UserRepository;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class CommentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CommentRepository $repo;
    private int $ticketId;
    private int $userId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->repo = new CommentRepository($this->pdo);

        $userRepo = new UserRepository($this->pdo);
        $catRepo = new CategoryRepository($this->pdo);
        $ticketRepo = new TicketRepository($this->pdo);

        $this->userId = (int) $userRepo->insert(new User(null, 'u@t.local', 'h', 'U', 'agent', null))->id;
        $catId = (int) $catRepo->insert(new Category(null, 'General', 72))->id;
        $this->ticketId = (int) $ticketRepo->insert(
            new Ticket(null, 's', 'd', 'new', null, $this->userId, $catId)
        )->id;
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testInsertAndFindByTicket(): void
    {
        $saved = $this->repo->insert(new Comment(null, $this->ticketId, $this->userId, 'First note'));

        $this->assertNotNull($saved->id);

        $all = $this->repo->findByTicket($this->ticketId);
        $this->assertCount(1, $all);
        $this->assertSame('First note', $all[0]->body);
    }

    public function testFindByTicketReturnsInCreationOrder(): void
    {
        $this->repo->insert(new Comment(null, $this->ticketId, $this->userId, 'one'));
        $this->repo->insert(new Comment(null, $this->ticketId, $this->userId, 'two'));

        $all = $this->repo->findByTicket($this->ticketId);
        $this->assertCount(2, $all);
        $this->assertSame('one', $all[0]->body);
        $this->assertSame('two', $all[1]->body);
    }

    public function testFindByTicketReturnsEmptyWhenNoComments(): void
    {
        $this->assertSame([], $this->repo->findByTicket(99999));
    }
}
