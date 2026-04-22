<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Ticket;
use PDO;

final class TicketRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(Ticket $ticket): Ticket
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (subject, description, status, assignee_user_id, requester_user_id, category_id)
             VALUES (:subject, :description, :status, :assignee, :requester, :category)'
        );
        $stmt->execute([
            ':subject' => $ticket->subject,
            ':description' => $ticket->description,
            ':status' => $ticket->status,
            ':assignee' => $ticket->assigneeUserId,
            ':requester' => $ticket->requesterUserId,
            ':category' => $ticket->categoryId,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('insert failed');
    }

    public function findById(int $id): ?Ticket
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Ticket::fromRow($row) : null;
    }

    /**
     * @return list<Ticket>
     *
     * Returns raw ticket rows. Callers fetch related entities themselves.
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM tickets ORDER BY id ASC')->fetchAll();
        return array_map(Ticket::fromRow(...), $rows);
    }

    /**
     * @return list<Ticket>
     */
    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE status = :status ORDER BY id ASC');
        $stmt->execute([':status' => $status]);
        return array_map(Ticket::fromRow(...), $stmt->fetchAll());
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE tickets SET status = :status WHERE id = :id');
        $stmt->execute([':id' => $id, ':status' => $status]);
    }
}
