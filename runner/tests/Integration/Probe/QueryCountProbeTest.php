<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Integration\Probe;

use LlmDispatch\Runner\Probe\QueryCountProbe;
use PHPUnit\Framework\TestCase;

final class QueryCountProbeTest extends TestCase
{
    public function testCountsQueriesForHealthEndpoint(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $worktreePath = dirname($repoRoot);
        $mockProject = $worktreePath . '/mock-project';

        if (!is_file($mockProject . '/vendor/autoload.php')) {
            $this->markTestSkipped('mock-project vendor/ not installed');
        }

        $probe = new QueryCountProbe();

        $count = $probe->count($worktreePath, '/api/health');

        $this->assertGreaterThanOrEqual(0, $count);
        $this->assertLessThan(5, $count, 'Health endpoint should produce near-zero queries');
    }

    public function testCountsHigherForTicketsIndexDueToNaturalNPlusOne(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $worktreePath = dirname($repoRoot);
        $mockProject = $worktreePath . '/mock-project';

        if (!is_file($mockProject . '/vendor/autoload.php')) {
            $this->markTestSkipped('mock-project vendor/ not installed');
        }

        $probe = new QueryCountProbe();

        $count = $probe->count($worktreePath, '/tickets', authAsAdmin: true);

        $this->assertGreaterThan(10, $count, 'Expected N+1 pattern; got ' . $count);
    }
}
