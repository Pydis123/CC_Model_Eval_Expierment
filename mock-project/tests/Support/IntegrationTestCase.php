<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Http\I18nExtension;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Views\Twig;

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

    /**
     * Create a Twig instance with I18nExtension registered (keys returned as-is when no strings loaded).
     */
    protected function createTwig(): Twig
    {
        $twig = Twig::create(dirname(__DIR__, 2) . '/templates');
        $twig->addExtension(new I18nExtension(fn(string $locale) => []));
        return $twig;
    }
}
