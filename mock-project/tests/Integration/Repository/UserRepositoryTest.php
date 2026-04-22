<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepository;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->repo = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testInsertAssignsIdAndFindsByEmail(): void
    {
        $user = new User(
            id: null,
            email: 'alice@example.com',
            passwordHash: 'hash',
            name: 'Alice',
            role: 'agent',
            companyId: null,
        );

        $saved = $this->repo->insert($user);

        $this->assertNotNull($saved->id);
        $this->assertSame('alice@example.com', $saved->email);

        $found = $this->repo->findByEmail('alice@example.com');
        $this->assertNotNull($found);
        $this->assertSame($saved->id, $found->id);
    }

    public function testFindByEmailReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findByEmail('nope@example.com'));
    }

    public function testFindById(): void
    {
        $user = $this->repo->insert(new User(
            null, 'bob@example.com', 'h', 'Bob', 'admin', null
        ));

        $found = $this->repo->findById((int) $user->id);
        $this->assertNotNull($found);
        $this->assertSame('bob@example.com', $found->email);
    }

    public function testFindAllReturnsListInsertionOrder(): void
    {
        $this->repo->insert(new User(null, 'a@test.local', 'h', 'A', 'agent', null));
        $this->repo->insert(new User(null, 'b@test.local', 'h', 'B', 'agent', null));

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
        $this->assertSame('a@test.local', $all[0]->email);
        $this->assertSame('b@test.local', $all[1]->email);
    }
}
