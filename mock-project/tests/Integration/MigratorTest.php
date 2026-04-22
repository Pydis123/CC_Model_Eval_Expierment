<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Support\Migrator;
use App\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private PDO $pdo;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::pdo();
        $this->pdo->exec('DROP TABLE IF EXISTS schema_migrations');
        $this->pdo->exec('DROP TABLE IF EXISTS widgets');

        $this->tmpDir = sys_get_temp_dir() . '/migrator_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->tmpDir);
        $this->pdo->exec('DROP TABLE IF EXISTS widgets');
        $this->pdo->exec('DROP TABLE IF EXISTS schema_migrations');
    }

    public function testRunsAllMigrationsInOrder(): void
    {
        file_put_contents($this->tmpDir . '/001_create_widgets.sql',
            'CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(50))');
        file_put_contents($this->tmpDir . '/002_insert_widget.sql',
            "INSERT INTO widgets (id, name) VALUES (1, 'alpha')");

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $applied = $migrator->run();

        $this->assertSame(['001_create_widgets.sql', '002_insert_widget.sql'], $applied);

        $row = $this->pdo->query('SELECT name FROM widgets WHERE id = 1')->fetch();
        $this->assertSame('alpha', $row['name']);
    }

    public function testSkipsAlreadyAppliedMigrations(): void
    {
        file_put_contents($this->tmpDir . '/001_create_widgets.sql',
            'CREATE TABLE widgets (id INT PRIMARY KEY, name VARCHAR(50))');

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $migrator->run();
        $applied = $migrator->run();

        $this->assertSame([], $applied);
    }

    public function testRecordsAppliedMigrationsInSchemaTable(): void
    {
        file_put_contents($this->tmpDir . '/001_create_widgets.sql',
            'CREATE TABLE widgets (id INT PRIMARY KEY)');

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $migrator->run();

        $rows = $this->pdo->query('SELECT filename FROM schema_migrations ORDER BY filename')
            ->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame('001_create_widgets.sql', $rows[0]['filename']);
    }
}
