<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Analysis\Aggregator;
use LlmDispatch\Runner\Analysis\BootstrapSimulator;
use LlmDispatch\Runner\Cli\Command\ReportCommand;
use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\Report\FindingsWriter;
use PHPUnit\Framework\TestCase;

final class ReportCommandTest extends TestCase
{
    private string $tmpJsonl;
    private string $tmpOutput;

    protected function setUp(): void
    {
        $this->tmpJsonl = sys_get_temp_dir() . '/results_' . uniqid() . '.jsonl';
        $this->tmpOutput = sys_get_temp_dir() . '/findings_' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpJsonl);
        @unlink($this->tmpOutput);
    }

    private function miniConfig(): Config
    {
        return new Config(
            schemaVersion: 1,
            experimentName: 'test',
            planSeed: 42,
            nReplicates: 2,
            maxIterationsPerRun: 3,
            maxWallClockSeconds: 1800,
            tiers: ['haiku', 'sonnet'],
            taskIds: ['task-a'],
            pinnedModels: ['haiku' => null, 'sonnet' => null],
            policy: 'retry-only',
            db: [],
        );
    }

    private function writeCompleteJsonl(): void
    {
        $lines = [];
        foreach (['haiku', 'sonnet'] as $tier) {
            foreach ([1, 2] as $n) {
                $lines[] = json_encode([
                    'run_id' => "task-a-{$tier}-{$n}",
                    'task_id' => 'task-a',
                    'model_tier' => $tier,
                    'model_id' => "claude-{$tier}-stub",
                    'n' => $n,
                    'outcome' => 'passed',
                    'iterations_used' => 1,
                    'tokens_subagent_in' => 10_000,
                    'tokens_subagent_out' => 2_000,
                    'tokens_pm_overhead' => 500,
                    'wall_clock_subagent_s' => 150,
                    'wall_clock_total_s' => 155,
                    'timestamp_start' => '2026-04-23T10:00:00Z',
                    'timestamp_end' => '2026-04-23T10:02:35Z',
                ], JSON_THROW_ON_ERROR);
            }
        }
        file_put_contents($this->tmpJsonl, implode("\n", $lines) . "\n");
    }

    private function makeCommand(): ReportCommand
    {
        return new ReportCommand(
            config: $this->miniConfig(),
            aggregator: new Aggregator(),
            simulator: new BootstrapSimulator(),
            writer: new FindingsWriter(),
            defaultInputPath: $this->tmpJsonl,
            defaultOutputPath: $this->tmpOutput,
            defaultBootstrapSamples: 100,
            defaultBootstrapSeed: 42,
        );
    }

    public function testHappyPathReturnsZeroAndWritesOutput(): void
    {
        $this->writeCompleteJsonl();

        ob_start();
        $exit = $this->makeCommand()->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpOutput);
        $content = (string) file_get_contents($this->tmpOutput);
        $this->assertStringContainsString('# LLM Dispatch Experiment — Findings', $content);
    }

    public function testExitOneOnIncompleteResults(): void
    {
        $line = json_encode([
            'run_id' => 'only-one',
            'task_id' => 'task-a',
            'model_tier' => 'haiku',
            'model_id' => 'stub',
            'n' => 1,
            'outcome' => 'passed',
            'iterations_used' => 1,
            'tokens_subagent_in' => 10_000,
            'tokens_subagent_out' => 2_000,
            'tokens_pm_overhead' => 500,
            'wall_clock_subagent_s' => 150,
            'wall_clock_total_s' => 155,
            'timestamp_start' => '2026-04-23T10:00:00Z',
            'timestamp_end' => '2026-04-23T10:02:35Z',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($this->tmpJsonl, $line . "\n");

        ob_start();
        $exit = $this->makeCommand()->run([]);
        ob_end_clean();

        $this->assertSame(1, $exit);
    }

    public function testExitTwoOnMissingInput(): void
    {
        ob_start();
        $exit = $this->makeCommand()->run([]);
        ob_end_clean();

        $this->assertSame(2, $exit);
    }

    public function testFlagsOverrideDefaults(): void
    {
        $this->writeCompleteJsonl();
        $altOutput = sys_get_temp_dir() . '/alt_' . uniqid() . '.md';

        ob_start();
        $exit = $this->makeCommand()->run([
            '--output=' . $altOutput,
            '--bootstrap-samples=50',
            '--bootstrap-seed=7',
        ]);
        ob_end_clean();

        $this->assertSame(0, $exit);
        $this->assertFileExists($altOutput);
        $this->assertFileDoesNotExist($this->tmpOutput);
        $content = (string) file_get_contents($altOutput);
        $this->assertStringContainsString('50 samples', $content);
        $this->assertStringContainsString('seed=7', $content);

        @unlink($altOutput);
    }

    public function testDeterministicOutputAcrossRuns(): void
    {
        $this->writeCompleteJsonl();
        $outA = sys_get_temp_dir() . '/a_' . uniqid() . '.md';
        $outB = sys_get_temp_dir() . '/b_' . uniqid() . '.md';

        ob_start();
        $this->makeCommand()->run(['--output=' . $outA]);
        $this->makeCommand()->run(['--output=' . $outB]);
        ob_end_clean();

        $stripGenerated = static fn(string $s): string => preg_replace('/\*\*Generated:\*\* [^\n]+\n/', '', $s) ?? $s;

        $a = $stripGenerated((string) file_get_contents($outA));
        $b = $stripGenerated((string) file_get_contents($outB));
        $this->assertSame($a, $b);

        @unlink($outA);
        @unlink($outB);
    }
}
