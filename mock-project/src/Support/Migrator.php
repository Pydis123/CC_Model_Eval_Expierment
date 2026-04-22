<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
    ) {}

    /**
     * @return list<string> filenames applied during this run
     */
    public function run(): array
    {
        $this->ensureSchemaTable();

        $applied = [];
        foreach ($this->pendingMigrations() as $filename) {
            $path = $this->migrationsDir . '/' . $filename;
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Cannot read migration: {$path}");
            }

            $this->pdo->exec($sql);

            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (filename, applied_at) VALUES (?, NOW())'
            );
            $stmt->execute([$filename]);

            $applied[] = $filename;
        }

        return $applied;
    }

    private function ensureSchemaTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations dir not found: {$this->migrationsDir}");
        }

        $all = glob($this->migrationsDir . '/*.sql');
        if ($all === false) {
            return [];
        }
        sort($all);

        $applied = $this->pdo->query('SELECT filename FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $appliedSet = array_flip($applied);

        $pending = [];
        foreach ($all as $path) {
            $filename = basename($path);
            if (!isset($appliedSet[$filename])) {
                $pending[] = $filename;
            }
        }

        return $pending;
    }
}
