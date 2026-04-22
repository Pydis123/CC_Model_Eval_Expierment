<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class Company
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
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
            name: (string) $row['name'],
            createdAt: isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
        );
    }
}
