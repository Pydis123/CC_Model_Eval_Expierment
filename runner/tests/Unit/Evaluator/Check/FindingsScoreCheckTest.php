<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Evaluator\Check;

use LlmDispatch\Runner\Dispatch\ClaudeCli;
use LlmDispatch\Runner\Dispatch\ClaudeCliResponse;
use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use LlmDispatch\Runner\Evaluator\Check\FindingsScoreCheck;
use LlmDispatch\Runner\Judge\JudgeClient;
use PHPUnit\Framework\TestCase;

final class FindingsScoreCheckTest extends TestCase
{
    public function testAllMatchedNeedsNoJudgeAndPasses(): void
    {
        $wt = $this->worktreeWithArtifact((string) json_encode(['findings' => [
            ['file' => 'src/A.php', 'line' => 100, 'defect_class' => 'sqli', 'explanation' => 'x'],
        ]]));
        $gtPath = $this->groundTruthFile([['id' => 'd1', 'file' => 'src/A.php',
            'defect_class' => 'sqli', 'line' => 100, 'span_start' => null, 'span_end' => null,
            'detectability_proof' => 'p']]);
        $check = new FindingsScoreCheck($gtPath, 'findings.json', 0.5, 0.6, 25, null);
        $result = $check->run($wt);
        $this->assertTrue($result->passed);
        $this->assertEqualsWithDelta(1.0, $result->details['metrics']['recall'], 0.001);
    }

    public function testMissingArtifactFailsWithFlag(): void
    {
        $wt = $this->emptyWorktree();
        $check = new FindingsScoreCheck($this->groundTruthFile([]), 'findings.json', 0.5, 0.6, 25, null);
        $result = $check->run($wt);
        $this->assertFalse($result->passed);
        $this->assertTrue($result->details['artifact_missing']);
    }

    public function testHallucinationVerdictLowersAdjustedPrecision(): void
    {
        $wt = $this->worktreeWithArtifact((string) json_encode(['findings' => [
            ['file' => 'src/A.php', 'line' => 100, 'defect_class' => 'sqli', 'explanation' => 'x'],
            ['file' => 'src/B.php', 'line' => 5, 'defect_class' => 'xss', 'explanation' => 'y'],
        ]]));
        $gtPath = $this->groundTruthFile([['id' => 'd1', 'file' => 'src/A.php',
            'defect_class' => 'sqli', 'line' => 100, 'span_start' => null, 'span_end' => null,
            'detectability_proof' => 'p']]);
        $judge = new JudgeClient($this->fakeCli(['{"verdict": "hallucination"}']), 'claude-opus-4-8', '/tmp');
        $check = new FindingsScoreCheck($gtPath, 'findings.json', 0.5, 0.6, 25, $judge);
        $result = $check->run($wt);
        $this->assertFalse($result->passed);
        $this->assertEqualsWithDelta(0.5, $result->details['metrics']['precision_adjusted'], 0.001);
        $this->assertSame(1, $result->details['metrics']['hallucinations']);
    }

    public function testRecallBelowThresholdFails(): void
    {
        $wt = $this->worktreeWithArtifact((string) json_encode(['findings' => [
            ['file' => 'src/A.php', 'line' => 100, 'defect_class' => 'sqli', 'explanation' => 'x'],
        ]]));
        $gtPath = $this->groundTruthFile([
            ['id' => 'd1', 'file' => 'src/A.php', 'defect_class' => 'sqli', 'line' => 100,
                'span_start' => null, 'span_end' => null, 'detectability_proof' => 'p'],
            ['id' => 'd2', 'file' => 'src/B.php', 'defect_class' => 'xss', 'line' => 10,
                'span_start' => null, 'span_end' => null, 'detectability_proof' => 'p'],
        ]);
        $check = new FindingsScoreCheck($gtPath, 'findings.json', 0.6, 0.6, 25, null);
        $result = $check->run($wt);
        $this->assertFalse($result->passed);
        $this->assertEqualsWithDelta(0.5, $result->details['metrics']['recall'], 0.001);
    }

    private function worktreeWithArtifact(string $json): string
    {
        $dir = $this->emptyWorktree();
        mkdir($dir . '/mock-project');
        file_put_contents($dir . '/mock-project/findings.json', $json);
        return $dir;
    }

    private function emptyWorktree(): string
    {
        $dir = sys_get_temp_dir() . '/fsc_' . bin2hex(random_bytes(6));
        mkdir($dir, 0o777, true);
        return $dir;
    }

    /**
     * @param list<array<string, mixed>> $defects
     */
    private function groundTruthFile(array $defects): string
    {
        $path = sys_get_temp_dir() . '/gt_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($path, (string) json_encode(['defects' => $defects]));
        return $path;
    }

    /** @param list<string> $texts */
    public function testTriagePromptCarriesExcerptFromWorktreeFile(): void
    {
        // Build worktree with real source file containing 100 numbered lines
        $wt = $this->emptyWorktree();
        mkdir($wt . '/mock-project/src', 0o777, true);
        $lines = [];
        for ($i = 1; $i <= 100; $i++) {
            if ($i === 10) {
                $lines[] = '// M010.';
            } elseif ($i === 20) {
                $lines[] = '// M020.';
            } elseif ($i === 60) {
                $lines[] = '// M060.';
            } elseif ($i === 100) {
                $lines[] = '// M100.';
            } else {
                $lines[] = '// line ' . $i;
            }
        }
        file_put_contents($wt . '/mock-project/src/A.php', implode("\n", $lines));

        // Create findings.json with one finding at line 60 (not in ground truth)
        file_put_contents($wt . '/mock-project/findings.json', (string) json_encode(['findings' => [
            ['file' => 'mock-project/src/A.php', 'line' => 60, 'defect_class' => 'unknown_class', 'explanation' => 'test'],
        ]]));

        // Ground truth has a defect in a different file so it is non-empty
        $gtPath = $this->groundTruthFile([['id' => 'd1', 'file' => 'src/B.php',
            'defect_class' => 'sqli', 'line' => 50, 'span_start' => null, 'span_end' => null,
            'detectability_proof' => 'p']]);

        // Create fake CLI that captures prompts
        $fakeCli = $this->fakeCli(['{"verdict": "hallucination"}']);
        $judge = new JudgeClient($fakeCli, 'claude-opus-4-8', '/tmp');
        $check = new FindingsScoreCheck($gtPath, 'findings.json', 0.5, 0.6, 25, $judge);
        $result = $check->run($wt);

        // Should have triggered judge and recorded a hallucination
        $this->assertFalse($result->passed);
        $this->assertSame(1, $result->details['metrics']['hallucinations']);

        // Verify the prompt was captured and contains expected content
        $this->assertCount(1, $fakeCli->prompts);
        $prompt = $fakeCli->prompts[0];

        // Assert excerpt contains markers within ±40 window (lines 20-100 for line 60)
        $this->assertStringContainsString('// M060.', $prompt, 'Prompt should contain the target line marker');
        $this->assertStringContainsString('// M020.', $prompt, 'Prompt should contain lower window boundary');
        $this->assertStringContainsString('// M100.', $prompt, 'Prompt should contain upper window boundary');

        // Assert excerpt does NOT contain markers outside window
        $this->assertStringNotContainsString('// M010.', $prompt, 'Prompt should not contain line 10 (outside window)');

        // Assert no fallback message
        $this->assertStringNotContainsString('(file not present)', $prompt, 'Prompt should contain actual file content');

        // Assert template line is present
        $this->assertStringContainsString('Reply ONLY with JSON', $prompt, 'Prompt should contain template instruction');
    }

    private function fakeCli(array $texts): ClaudeCli
    {
        return new class($texts) implements ClaudeCli {
            public int $calls = 0;
            /** @var list<string> */
            public array $prompts = [];

            /** @param list<string> $texts */
            public function __construct(private array $texts) {}

            public function dispatch(string $prompt, string $modelId, string $cwd, array $allowedTools): ClaudeCliResponse
            {
                $this->prompts[] = $prompt;
                $text = $this->texts[$this->calls] ?? '';
                $this->calls++;
                return new ClaudeCliResponse(
                    isError: false, resultText: $text, modelIdReported: $modelId,
                    inputTokens: 1, outputTokens: 1, durationMs: 1, stopReason: 'end_turn',
                    costUsd: 0.0, rateLimit: new RateLimitInfo('ok', null),
                    rawStdout: '', rawStderr: '', exitCode: 0,
                );
            }
        };
    }
}
