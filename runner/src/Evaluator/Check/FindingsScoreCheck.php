<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use InvalidArgumentException;
use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\Findings\Finding;
use LlmDispatch\Runner\Evaluator\Findings\FindingsMatcher;
use LlmDispatch\Runner\Evaluator\Findings\GroundTruth;
use LlmDispatch\Runner\Judge\JudgeClient;
use RuntimeException;

final class FindingsScoreCheck implements CheckInterface
{
    public function __construct(
        private readonly string $groundTruthPath,
        private readonly string $artifactRelPath,
        private readonly float $recallMin,
        private readonly float $precisionMin,
        private readonly int $findingsCap,
        private readonly ?JudgeClient $judge,
    ) {}

    public function run(string $worktreePath): CheckResult
    {
        $artifactPath = $worktreePath . '/mock-project/' . $this->artifactRelPath;

        $raw = @file_get_contents($artifactPath);
        if ($raw === false) {
            return $this->missing();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['findings']) || !is_array($decoded['findings'])) {
            return $this->missing();
        }

        $findings = [];
        $malformed = 0;
        foreach ($decoded['findings'] as $entry) {
            if (!is_array($entry)) {
                $malformed++;
                continue;
            }
            try {
                $findings[] = Finding::fromArray($entry);
            } catch (InvalidArgumentException) {
                $malformed++;
            }
        }

        $truncated = count($findings) > $this->findingsCap;
        if ($truncated) {
            $findings = array_slice($findings, 0, $this->findingsCap);
        }

        $gt = GroundTruth::fromFile($this->groundTruthPath);
        $matcher = new FindingsMatcher();
        $outcome = $matcher->match($findings, $gt);

        if ($this->judge === null && $outcome->unmatched !== []) {
            return new CheckResult(
                type: 'findings_score',
                passed: false,
                details: ['error' => 'judge required but not configured'],
            );
        }

        $realUnseeded = 0;
        $judgeDuplicates = 0;
        $hallucinations = 0;
        $verdicts = [];
        $judgeErrors = [];

        foreach ($outcome->unmatched as $finding) {
            $prompt = $this->buildPrompt($worktreePath, $finding, $gt);

            try {
                $judged = $this->judge->judgeJson($prompt);
                $verdict = $judged['verdict'] ?? null;
            } catch (RuntimeException $e) {
                $hallucinations++;
                $verdicts[] = 'hallucination';
                $judgeErrors[] = $e->getMessage();
                continue;
            }

            switch ($verdict) {
                case 'real_unseeded':
                    $realUnseeded++;
                    $verdicts[] = 'real_unseeded';
                    break;
                case 'duplicate':
                    $judgeDuplicates++;
                    $verdicts[] = 'duplicate';
                    break;
                default:
                    $hallucinations++;
                    $verdicts[] = 'hallucination';
                    break;
            }
        }

        $total = count($findings);
        $dup = $outcome->duplicates + $judgeDuplicates;

        $metrics = [
            'recall' => $outcome->recall(count($gt->defects)),
            'precision_mechanical' => $total > 0 ? $outcome->truePositives / $total : 0.0,
            'precision_adjusted' => ($total - $dup) > 0
                ? ($outcome->truePositives + $realUnseeded) / ($total - $dup) : 0.0,
            'true_positives' => $outcome->truePositives,
            'false_positives' => $hallucinations,
            'duplicates' => $dup,
            'hallucinations' => $hallucinations,
            'real_unseeded' => $realUnseeded,
            'missed_defect_ids' => $outcome->missedDefectIds,
            'judge_verdicts' => $verdicts,
        ];
        $metrics['f1'] = ($metrics['recall'] + $metrics['precision_adjusted']) > 0.0
            ? 2 * $metrics['recall'] * $metrics['precision_adjusted']
                / ($metrics['recall'] + $metrics['precision_adjusted'])
            : 0.0;

        $passed = $metrics['recall'] >= $this->recallMin
            && $metrics['precision_adjusted'] >= $this->precisionMin;

        $details = [
            'metrics' => $metrics,
            'malformed_findings' => $malformed,
            'truncated' => $truncated,
        ];
        if ($judgeErrors !== []) {
            $details['judge_errors'] = $judgeErrors;
        }

        return new CheckResult(
            type: 'findings_score',
            passed: $passed,
            details: $details,
        );
    }

    private function missing(): CheckResult
    {
        return new CheckResult(
            type: 'findings_score',
            passed: false,
            details: ['artifact_missing' => true, 'metrics' => null],
        );
    }

    private function buildPrompt(string $worktreePath, Finding $finding, GroundTruth $gt): string
    {
        $relFile = $finding->file;
        if (str_starts_with($relFile, 'mock-project/')) {
            $relFile = substr($relFile, strlen('mock-project/'));
        }

        $from = max(1, $finding->line - 40);
        $to = $finding->line + 40;
        $excerpt = $this->readExcerpt($worktreePath . '/mock-project/' . $relFile, $from, $to);

        $seeded = [];
        foreach ($gt->defects as $defect) {
            if ($defect->file === $finding->file) {
                $seeded[] = $defect->defectClass;
            }
        }
        $seededClasses = implode(', ', $seeded);

        return "You are auditing one finding from a code review. Classify it.\n"
            . "Finding: {$finding->file}:{$finding->line} [{$finding->defectClass}] {$finding->explanation}\n"
            . "Code excerpt (lines {$from}-{$to} of {$finding->file}):\n"
            . "{$excerpt}\n"
            . "Known seeded defects in this file (do not reveal): {$seededClasses}\n"
            . 'Reply ONLY with JSON: {"verdict": "real_unseeded" | "duplicate" | "hallucination"}';
    }

    private function readExcerpt(string $path, int $from, int $to): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return '(file not present)';
        }

        $lines = explode("\n", $content);
        $slice = array_slice($lines, $from - 1, $to - $from + 1);

        return implode("\n", $slice);
    }
}
