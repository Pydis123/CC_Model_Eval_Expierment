<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\ReportDeltaCommand;
use PHPUnit\Framework\TestCase;

final class ReportDeltaCommandTest extends TestCase
{
    public function testKeepsOnlyLastRowPerRunIdWhenComputingDelta(): void
    {
        $dir = sys_get_temp_dir() . '/report-delta-test-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $oldPath = $dir . '/old.jsonl';
        $newPath = $dir . '/new.jsonl';
        $outPath = $dir . '/delta.md';

        $row = static fn (string $runId, string $outcome, string $disposition): string => json_encode([
            'run_id' => $runId,
            'task_id' => 'task-a',
            'model_tier' => 'sonnet',
            'n' => 1,
            'outcome' => $outcome,
            'dispatch_disposition' => $disposition,
            'tokens_subagent_in' => 100,
            'tokens_subagent_out' => 900,
        ], JSON_THROW_ON_ERROR);

        file_put_contents($oldPath, $row('v1-a', 'passed', 'completed') . "\n");
        // A network-error row later superseded by a re-run of the same run_id.
        file_put_contents($newPath, implode("\n", [
            $row('v2-a', 'failed', 'error'),
            $row('v2-a', 'passed', 'completed'),
        ]) . "\n");

        $cmd = new ReportDeltaCommand($oldPath, $newPath, $outPath);
        $this->expectOutputRegex('/^Wrote /');
        $exit = $cmd->run([]);

        $md = (string) file_get_contents($outPath);
        self::assertSame(0, $exit);
        self::assertStringContainsString('| task-a |', $md);
        self::assertStringContainsString('| 100% | 100% |', $md);
    }
}
