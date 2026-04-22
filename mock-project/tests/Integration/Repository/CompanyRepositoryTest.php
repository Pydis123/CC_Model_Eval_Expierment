<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\Company;
use App\Domain\Repository\CompanyRepository;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class CompanyRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CompanyRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
        $this->repo = new CompanyRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testInsertAndFindById(): void
    {
        $saved = $this->repo->insert(new Company(null, 'Acme AB'));

        $this->assertNotNull($saved->id);
        $found = $this->repo->findById((int) $saved->id);
        $this->assertNotNull($found);
        $this->assertSame('Acme AB', $found->name);
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->repo->findById(99999));
    }

    public function testFindAllReturnsAllCompaniesByName(): void
    {
        $this->repo->insert(new Company(null, 'Globex AB'));
        $this->repo->insert(new Company(null, 'Acme AB'));

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
        $this->assertSame('Acme AB', $all[0]->name);
        $this->assertSame('Globex AB', $all[1]->name);
    }
}
