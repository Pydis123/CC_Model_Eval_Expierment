<?php

declare(strict_types=1);

namespace App\Domain\Service;

use PDO;

final class RecentActivityService
{
    public function __construct(private readonly PDO $pdo) {}

    public function countRecentTickets(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM tickets
             WHERE created_at >= NOW() - INTERVAL 1 SECOND'
        );
        return (int) $stmt->fetchColumn();
    }
}
