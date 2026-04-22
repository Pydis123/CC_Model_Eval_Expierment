<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Company;
use PDO;

final class CompanyRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(Company $company): Company
    {
        $stmt = $this->pdo->prepare('INSERT INTO companies (name) VALUES (:name)');
        $stmt->execute([':name' => $company->name]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('insert failed');
    }

    public function findById(int $id): ?Company
    {
        $stmt = $this->pdo->prepare('SELECT * FROM companies WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Company::fromRow($row) : null;
    }

    /**
     * @return list<Company>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM companies ORDER BY name ASC')->fetchAll();
        return array_map(Company::fromRow(...), $rows);
    }
}
