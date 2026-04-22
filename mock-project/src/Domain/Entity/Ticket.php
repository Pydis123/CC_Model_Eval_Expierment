<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class Ticket
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $subject,
        public readonly string $description,
        public readonly string $status,
        public readonly ?int $assigneeUserId,
        public readonly int $requesterUserId,
        public readonly int $categoryId,
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
            subject: (string) $row['subject'],
            description: (string) $row['description'],
            status: (string) $row['status'],
            assigneeUserId: isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : null,
            requesterUserId: (int) $row['requester_user_id'],
            categoryId: (int) $row['category_id'],
            createdAt: isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
        );
    }
}
