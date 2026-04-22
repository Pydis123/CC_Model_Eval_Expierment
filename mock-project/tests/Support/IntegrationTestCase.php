<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
