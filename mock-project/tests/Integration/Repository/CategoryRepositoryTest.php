<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\Category;
use App\Domain\Repository\CategoryRepository;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class CategoryRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CategoryRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->repo = new CategoryRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testInsertAndFindById(): void
    {
        $saved = $this->repo->insert(new Category(null, 'Technical', 24));

        $this->assertNotNull($saved->id);
        $found = $this->repo->findById((int) $saved->id);
        $this->assertNotNull($found);
        $this->assertSame('Technical', $found->name);
        $this->assertSame(24, $found->defaultSlaHours);
    }

    public function testFindAllOrderedByName(): void
    {
        $this->repo->insert(new Category(null, 'Billing', 48));
        $this->repo->insert(new Category(null, 'Technical', 24));

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
        $this->assertSame('Billing', $all[0]->name);
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findById(99999));
    }
}
