<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testAllCoreTablesExist(): void
    {
        $pdo = TestDatabase::pdo();
        $rows = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('companies', $rows);
        $this->assertContains('users', $rows);
        $this->assertContains('categories', $rows);
        $this->assertContains('tickets', $rows);
        $this->assertContains('comments', $rows);
        $this->assertContains('i18n_strings', $rows);
        $this->assertContains('schema_migrations', $rows);
    }

    public function testTicketsHasExpectedColumns(): void
    {
        $pdo = TestDatabase::pdo();
        $cols = $pdo->query('SHOW COLUMNS FROM tickets')->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('id', $cols);
        $this->assertContains('subject', $cols);
        $this->assertContains('status', $cols);
        $this->assertContains('assignee_user_id', $cols);
        $this->assertContains('requester_user_id', $cols);
        $this->assertContains('category_id', $cols);
        $this->assertNotContains('sla_deadline', $cols, 'sla_deadline is not part of the tickets schema');
    }

    public function testForeignKeysAreEnforced(): void
    {
        $pdo = TestDatabase::pdo();

        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO tickets (subject, description, status, requester_user_id, category_id)
                    VALUES ('x', 'y', 'new', 9999, 9999)");
    }
}
