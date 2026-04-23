<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\Check\QueryCountCheck;
use LlmDispatch\Runner\Probe\QueryCountProbe;
use PHPUnit\Framework\TestCase;

final class QueryCountCheckTest extends TestCase
{
    public function testPassesWhenActualAtMostMax(): void
    {
        $stub = $this->stubProbe(5);
        $check = new QueryCountCheck(route: '/tickets', max: 5, probe: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertTrue($result->passed);
        $this->assertSame('query_count', $result->type);
        $this->assertSame(['route' => '/tickets', 'max' => 5, 'actual' => 5], $result->details);
    }

    public function testFailsWhenActualAboveMax(): void
    {
        $stub = $this->stubProbe(31);
        $check = new QueryCountCheck(route: '/tickets', max: 5, probe: $stub);

        $result = $check->run('/tmp/worktree');

        $this->assertFalse($result->passed);
        $this->assertSame(31, $result->details['actual']);
    }

    public function testPassesAuthAsAdminFlagToProbe(): void
    {
        $capturedAuth = null;
        $stub = new class($capturedAuth) extends QueryCountProbe {
            public function __construct(public mixed &$capturedAuth) {}
            public function count(string $worktreePath, string $route, bool $authAsAdmin = false): int
            {
                $this->capturedAuth = $authAsAdmin;
                return 3;
            }
        };

        $check = new QueryCountCheck(route: '/tickets', max: 5, authAsAdmin: true, probe: $stub);
        $check->run('/tmp/worktree');

        $this->assertTrue($capturedAuth);
    }

    private function stubProbe(int $count): QueryCountProbe
    {
        return new class($count) extends QueryCountProbe {
            public function __construct(private int $count) {}
            public function count(string $worktreePath, string $route, bool $authAsAdmin = false): int
            {
                return $this->count;
            }
        };
    }
}
