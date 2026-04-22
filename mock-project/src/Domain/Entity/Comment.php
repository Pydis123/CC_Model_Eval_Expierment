<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class Comment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $ticketId,
        public readonly int $authorUserId,
        public readonly string $body,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            ticketId: (int) $row['ticket_id'],
            authorUserId: (int) $row['author_user_id'],
            body: (string) $row['body'],
            createdAt: isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
        );
    }
}
