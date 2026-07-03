<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Integration\Cli;

use PHPUnit\Framework\TestCase;

final class CliApplicationTest extends TestCase
{
    private string $repoRoot;
    private string $tempDir;
    private string $statePath;
    private string $resultsPath;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 4);
        $this->tempDir = sys_get_temp_dir() . '/cliapp_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->statePath = $this->tempDir . '/state.json';
        $this->resultsPath = $this->tempDir . '/results.jsonl';
    }

    protected function tearDown(): void
    {
        $this->rrm($this->tempDir);
    }

    public function testStateInitNextRunAndPinModelsFlow(): void
    {
        file_put_contents($this->statePath, json_encode(['schema_version' => 1]));

        $initResult = $this->cli('state', 'init', '--force');
        $this->assertSame(0, $initResult['exit'], 'stderr: ' . $initResult['stderr']);
        $json = json_decode($initResult['stdout'], true);
        $this->assertSame(160, $json['runs_queued']);

        $peekResult = $this->cli('state', 'next-run', '--peek');
        $this->assertSame(0, $peekResult['exit']);
        $peekJson = json_decode($peekResult['stdout'], true);
        $this->assertArrayHasKey('run_id', $peekJson);
        $this->assertArrayHasKey('task_id', $peekJson);
        $this->assertNull($peekJson['claimed_at']);

        $claimResult = $this->cli('state', 'next-run');
        $this->assertSame(0, $claimResult['exit']);
        $claimJson = json_decode($claimResult['stdout'], true);
        $this->assertSame($peekJson['run_id'], $claimJson['run_id']);
        $this->assertNotNull($claimJson['claimed_at']);

        $pinResult = $this->cli(
            'state', 'pin-models',
            '--haiku=claude-haiku-4-5-20251001',
            '--sonnet=claude-sonnet-4-6',
            '--opus=claude-opus-4-7',
            '--fable=claude-fable-5',
        );
        $this->assertSame(0, $pinResult['exit']);

        $state = json_decode(file_get_contents($this->statePath), true);
        $this->assertSame('claude-opus-4-7', $state['pinned_models']['opus']);
    }

    public function testUnknownCommandReturnsUsageExitTwo(): void
    {
        $result = $this->cli('bogus');
        $this->assertSame(2, $result['exit']);
        $this->assertStringContainsString('Usage:', $result['stderr']);
    }

    /**
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function cli(string ...$args): array
    {
        $bin = $this->repoRoot . '/runner/bin/cli';
        $cmd = array_merge([$bin], $args);

        $env = ['LLM_DISPATCH_STATE_PATH' => $this->statePath, 'LLM_DISPATCH_RESULTS_PATH' => $this->resultsPath] + getenv();

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes, $this->repoRoot, $env);

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit = proc_close($proc);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function rrm(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $this->rrm($path . '/' . $e);
        }
        @rmdir($path);
    }
}
