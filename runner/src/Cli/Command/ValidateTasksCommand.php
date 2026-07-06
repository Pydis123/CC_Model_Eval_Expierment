<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Evaluator\Findings\GroundTruth;
use LlmDispatch\Runner\Evaluator\RubricFile;
use Throwable;

final class ValidateTasksCommand implements CommandInterface
{
    public function __construct(
        private readonly string $tasksDir,
        private readonly string $repoRoot,
    ) {}

    public function run(array $args): int
    {
        $files = glob($this->tasksDir . '/*.json') ?: [];
        sort($files);

        $failCount = 0;
        $taskCount = 0;

        foreach ($files as $file) {
            if (basename($file) === 'schema.json') {
                continue;
            }

            $raw = @file_get_contents($file);
            $task = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($task) || !is_string($task['id'] ?? null)) {
                echo "WARN " . basename($file) . ": not a task definition\n";
                continue;
            }

            $taskCount++;
            $taskId = $task['id'];
            $reasons = $this->validateTask($task);

            if ($reasons === []) {
                echo "OK {$taskId}\n";
            } else {
                foreach ($reasons as $reason) {
                    echo "FAIL {$taskId}: {$reason}\n";
                    $failCount++;
                }
            }
        }

        if ($taskCount === 0) {
            echo "WARN no task definitions found in {$this->tasksDir}\n";
            return 0;
        }

        return $failCount > 0 ? 2 : 0;
    }

    /**
     * @param array<string, mixed> $task
     * @return list<string>
     */
    private function validateTask(array $task): array
    {
        $reasons = [];

        $promptFile = $task['prompt_file'] ?? null;
        if (!is_string($promptFile) || !is_file($this->tasksDir . '/' . $promptFile)) {
            $reasons[] = "prompt_file missing: " . (is_string($promptFile) ? $promptFile : '(none)');
        }

        $criteria = is_array($task['success_criteria'] ?? null) ? $task['success_criteria'] : [];
        foreach ($criteria as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $type = $criterion['type'] ?? null;
            if ($type === 'findings_score') {
                $reasons = [...$reasons, ...$this->validateFindingsScore($criterion)];
            } elseif ($type === 'rubric_score') {
                $reasons = [...$reasons, ...$this->validateRubricScore($criterion)];
            }
        }

        $exportRef = $task['export_ref'] ?? null;
        if (is_string($exportRef) && is_string($task['id'] ?? null)) {
            $fixturesDir = $this->repoRoot . '/tasks/fixtures/' . $task['id'] . '/';
            if (is_dir($fixturesDir)) {
                if (is_readable($fixturesDir)) {
                    echo "OK {$task['id']}: fixtures dir readable\n";
                } else {
                    $reasons[] = "fixtures dir not readable: {$fixturesDir}";
                }
            }
        }

        return $reasons;
    }

    /**
     * @param array<string, mixed> $criterion
     * @return list<string>
     */
    private function validateFindingsScore(array $criterion): array
    {
        $reasons = [];

        $groundTruth = $criterion['ground_truth'] ?? null;
        if (!is_string($groundTruth)) {
            $reasons[] = 'findings_score missing ground_truth';
        } else {
            try {
                GroundTruth::fromFile($this->repoRoot . '/tasks/ground-truth/' . $groundTruth);
            } catch (Throwable $e) {
                $reasons[] = $e->getMessage();
            }
        }

        if (!is_numeric($criterion['recall_min'] ?? null)) {
            $reasons[] = 'findings_score missing numeric recall_min';
        }
        if (!is_numeric($criterion['precision_min'] ?? null)) {
            $reasons[] = 'findings_score missing numeric precision_min';
        }

        return $reasons;
    }

    /**
     * @param array<string, mixed> $criterion
     * @return list<string>
     */
    private function validateRubricScore(array $criterion): array
    {
        $reasons = [];

        $rubric = $criterion['rubric'] ?? null;
        if (!is_string($rubric)) {
            $reasons[] = 'rubric_score missing rubric';
        } else {
            $criteria = RubricFile::loadCriteria($this->repoRoot . '/tasks/rubrics/' . $rubric);
            if ($criteria === []) {
                $reasons[] = 'rubric missing or invalid';
            }
        }

        if (!is_numeric($criterion['threshold'] ?? null)) {
            $reasons[] = 'rubric_score missing numeric threshold';
        }

        return $reasons;
    }
}
