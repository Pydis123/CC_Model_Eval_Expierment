<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\ProbeQueryCountCommand;
use LlmDispatch\Runner\Probe\QueryCountProbe;
use PHPUnit\Framework\TestCase;

final class ProbeQueryCountCommandTest extends TestCase
{
    public function testOutputsRouteAndCount(): void
    {
        $probe = new class extends QueryCountProbe {
            public function count(string $worktreePath, string $route, bool $authAsAdmin = false): int
            {
                return 31;
            }
        };

        $command = new ProbeQueryCountCommand($probe);

        ob_start();
        $exit = $command->run(['--worktree=/tmp/wt', '--route=/tickets']);
        $out = ob_get_clean();

        $this->assertSame(0, $exit);
        $json = json_decode($out, true);
        $this->assertSame('/tickets', $json['route']);
        $this->assertSame(31, $json['query_count']);
    }

    public function testPassesAuthFlagToProbe(): void
    {
        $captured = null;
        $probe = new class($captured) extends QueryCountProbe {
            public function __construct(public mixed &$captured) {}
            public function count(string $worktreePath, string $route, bool $authAsAdmin = false): int
            {
                $this->captured = $authAsAdmin;
                return 0;
            }
        };

        $command = new ProbeQueryCountCommand($probe);

        ob_start();
        $command->run(['--worktree=/tmp/wt', '--route=/tickets', '--auth-as-admin']);
        ob_end_clean();

        $this->assertTrue($captured);
    }

    public function testRejectsMissingArgs(): void
    {
        $command = new ProbeQueryCountCommand(new QueryCountProbe());

        ob_start();
        $exit = $command->run(['--worktree=/tmp/wt']);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }
}
