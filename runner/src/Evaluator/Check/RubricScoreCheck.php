<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use InvalidArgumentException;
use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\RubricFile;
use LlmDispatch\Runner\Judge\JudgeClient;
use RuntimeException;

final class RubricScoreCheck implements CheckInterface
{
    public function __construct(
        private readonly string $rubricPath,
        private readonly string $artifactRelPath,
        private readonly int $threshold,
        private readonly JudgeClient $judge,
        private readonly int $judgeCalls = 3,
    ) {
        if ($this->judgeCalls < 1) {
            throw new InvalidArgumentException('judgeCalls must be at least 1');
        }
    }

    public function run(string $worktreePath): CheckResult
    {
        $artifactPath = $worktreePath . '/mock-project/' . $this->artifactRelPath;

        $raw = @file_get_contents($artifactPath);
        if ($raw === false || trim($raw) === '') {
            return $this->missing();
        }

        [$blindedMemo, $blindedLines] = $this->blind($raw);
        $criteria = RubricFile::loadCriteria($this->rubricPath);

        if (empty($criteria)) {
            return new CheckResult(
                type: 'rubric_score',
                passed: false,
                details: [
                    'blinded_lines' => $blindedLines,
                    'error' => 'rubric missing or invalid',
                    'metrics' => null,
                ],
            );
        }

        $prompt = $this->buildPrompt($criteria, $blindedMemo);

        $rawScores = [];
        for ($i = 0; $i < $this->judgeCalls; $i++) {
            try {
                $reply = $this->judge->judgeJson($prompt);
            } catch (RuntimeException $e) {
                return new CheckResult(
                    type: 'rubric_score',
                    passed: false,
                    details: [
                        'blinded_lines' => $blindedLines,
                        'error' => $e->getMessage(),
                        'metrics' => null,
                    ],
                );
            }

            $scores = $reply['scores'] ?? null;
            $rawScores[] = is_array($scores) ? $scores : [];
        }

        $medians = [];
        foreach ($criteria as $criterion) {
            $criterionId = $criterion['id'];
            $votes = [];
            foreach ($rawScores as $scores) {
                $vote = $scores[$criterionId] ?? 0;
                $vote = is_numeric($vote) ? (int) $vote : 0;
                $votes[] = max(0, min(2, $vote));
            }
            sort($votes);
            $medians[$criterionId] = $votes[intdiv(count($votes), 2)];
        }

        $total = array_sum($medians);

        return new CheckResult(
            type: 'rubric_score',
            passed: $total >= $this->threshold,
            details: [
                'blinded_lines' => $blindedLines,
                'metrics' => [
                    'rubric_total' => $total,
                    'rubric_per_criterion' => $medians,
                    'rubric_raw_calls' => $rawScores,
                ],
            ],
        );
    }

    private function missing(): CheckResult
    {
        return new CheckResult(
            type: 'rubric_score',
            passed: false,
            details: ['artifact_missing' => true, 'metrics' => null],
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function blind(string $memo): array
    {
        $lines = explode("\n", $memo);
        $kept = [];
        $dropped = 0;
        foreach ($lines as $line) {
            if (preg_match('/claude|anthropic|fable|opus|sonnet|haiku/i', $line) === 1) {
                $dropped++;
                continue;
            }
            $kept[] = $line;
        }

        return [implode("\n", $kept), $dropped];
    }

    /**
     * @param list<array{id: string, text: string, anchors: array{0: string, 1: string, 2: string}}> $criteria
     */
    private function buildPrompt(array $criteria, string $memo): string
    {
        $lines = [];
        foreach ($criteria as $criterion) {
            $lines[] = "- {$criterion['id']}: {$criterion['text']} | "
                . "0={$criterion['anchors'][0]} 1={$criterion['anchors'][1]} 2={$criterion['anchors'][2]}";
        }
        $rubricBlock = implode("\n", $lines);

        return "Score this decision memo against the rubric. For each criterion give 0, 1 or 2\n"
            . "exactly as anchored. Be strict: award 2 only when the anchor for 2 is fully met.\n"
            . "RUBRIC:\n"
            . "{$rubricBlock}\n"
            . "MEMO:\n"
            . "{$memo}\n"
            . 'Reply ONLY with JSON: {"scores": {"<criterion id>": 0|1|2, ...}}';
    }
}
