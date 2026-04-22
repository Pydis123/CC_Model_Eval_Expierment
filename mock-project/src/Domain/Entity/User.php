<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $name,
        public readonly string $role,
        public readonly ?int $companyId,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            name: (string) $row['name'],
            role: (string) $row['role'],
            companyId: isset($row['company_id']) ? (int) $row['company_id'] : null,
            createdAt: isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
        );
    }
}
