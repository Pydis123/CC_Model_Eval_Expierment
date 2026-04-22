<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Comment;
use PDO;

final class CommentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function insert(Comment $comment): Comment
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO comments (ticket_id, author_user_id, body)
             VALUES (:ticket, :author, :body)'
        );
        $stmt->execute([
            ':ticket' => $comment->ticketId,
            ':author' => $comment->authorUserId,
            ':body' => $comment->body,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? throw new \RuntimeException('insert failed');
    }

    public function findById(int $id): ?Comment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM comments WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Comment::fromRow($row) : null;
    }

    /**
     * @return list<Comment>
     */
    public function findByTicket(int $ticketId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM comments WHERE ticket_id = :ticket ORDER BY id ASC'
        );
        $stmt->execute([':ticket' => $ticketId]);
        return array_map(Comment::fromRow(...), $stmt->fetchAll());
    }
}
