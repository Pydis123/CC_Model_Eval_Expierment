<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(User $user): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, name, role, company_id)
             VALUES (:email, :password_hash, :name, :role, :company_id)'
        );
        $stmt->execute([
            ':email' => $user->email,
            ':password_hash' => $user->passwordHash,
            ':name' => $user->name,
            ':role' => $user->role,
            ':company_id' => $user->companyId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('insert failed to return user');
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    /**
     * @return list<User>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();
        return array_map(User::fromRow(...), $rows);
    }
}
