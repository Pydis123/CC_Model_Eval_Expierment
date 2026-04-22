<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Category;
use PDO;

final class CategoryRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(Category $category): Category
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, default_sla_hours) VALUES (:name, :sla)'
        );
        $stmt->execute([
            ':name' => $category->name,
            ':sla' => $category->defaultSlaHours,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('insert failed');
    }

    public function findById(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Category::fromRow($row) : null;
    }

    /**
     * @return list<Category>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
        return array_map(Category::fromRow(...), $rows);
    }
}
