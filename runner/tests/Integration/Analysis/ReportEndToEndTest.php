<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Integration\Analysis;

use LlmDispatch\Runner\Analysis\Aggregator;
use LlmDispatch\Runner\Analysis\BootstrapSimulator;
use LlmDispatch\Runner\Cli\Command\ReportCommand;
use LlmDispatch\Runner\Config;
use LlmDispatch\Runner\Report\FindingsWriter;
use PHPUnit\Framework\TestCase;

final class ReportEndToEndTest extends TestCase
{
    private string $tmpOutput;

    protected function setUp(): void
    {
        $this->tmpOutput = sys_get_temp_dir() . '/findings_e2e_' . uniqid() . '.md';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpOutput);
    }

    private function fixtureConfig(): Config
    {
        return new Config(
            schemaVersion: 1,
            experimentName: 'fixture',
            planSeed: 42,
            nReplicates: 2,
            maxIterationsPerRun: 3,
            maxWallClockSeconds: 1800,
            tiers: ['haiku', 'sonnet', 'opus'],
            taskIds: ['task-a', 'task-b'],
            pinnedModels: ['haiku' => null, 'sonnet' => null, 'opus' => null],
            policy: 'retry-only',
            db: [],
        );
    }

    public function testFixtureProducesDeterministicGoldenFindings(): void
    {
        $fixtureJsonl = (string) realpath(__DIR__ . '/../../fixtures/analysis/complete_small.jsonl');
        $goldenPath = (string) realpath(__DIR__ . '/../../fixtures/analysis/golden_findings.md');

        $this->assertFileExists($fixtureJsonl, 'fixture JSONL missing');
        $this->assertFileExists($goldenPath, 'golden findings.md missing');

        $cmd = new ReportCommand(
            config: $this->fixtureConfig(),
            aggregator: new Aggregator(),
            simulator: new BootstrapSimulator(),
            writer: new FindingsWriter(),
            defaultInputPath: $fixtureJsonl,
            defaultOutputPath: $this->tmpOutput,
            defaultBootstrapSamples: 1000,
            defaultBootstrapSeed: 42,
        );

        ob_start();
        $exit = $cmd->run([]);
        ob_end_clean();

        $this->assertSame(0, $exit);

        $generated = (string) file_get_contents($this->tmpOutput);
        $golden = (string) file_get_contents($goldenPath);

        $stripGenerated = static fn(string $s): string => preg_replace('/\*\*Generated:\*\* [^\n]+\n/', '', $s) ?? $s;

        $this->assertSame($stripGenerated($golden), $stripGenerated($generated));
    }

    public function testSubprocessCliInvocation(): void
    {
        $cliPath = dirname(__DIR__, 3) . '/bin/cli';
        $fixtureJsonl = (string) realpath(__DIR__ . '/../../fixtures/analysis/complete_small.jsonl');

        $output = [];
        $exit = 0;
        exec(
            sprintf(
                '%s report --input=%s --output=%s 2>&1',
                escapeshellarg($cliPath),
                escapeshellarg($fixtureJsonl),
                escapeshellarg($this->tmpOutput),
            ),
            $output,
            $exit,
        );

        // Real experiment_config.json expects 8 tasks × 3 tiers × N=3;
        // the fixture has 2 tasks × 3 tiers × 2 — so we expect exit 1 (incomplete).
        $this->assertSame(1, $exit, "CLI stdout/stderr:\n" . implode("\n", $output));
        $this->assertStringContainsString('Incomplete results', implode("\n", $output));
    }
}
