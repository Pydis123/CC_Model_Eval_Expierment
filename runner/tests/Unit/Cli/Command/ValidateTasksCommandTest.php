<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Cli\Command;

use LlmDispatch\Runner\Cli\Command\ValidateTasksCommand;
use PHPUnit\Framework\TestCase;

final class ValidateTasksCommandTest extends TestCase
{
    public function testPassesWithValidFindingsTask(): void
    {
        $tasksDir = $this->tempDir();
        $repoRoot = $this->tempDir();

        file_put_contents($tasksDir . '/900-valid-findings.prompt.md', "Do the thing.\n");
        mkdir($repoRoot . '/tasks/ground-truth', 0o777, true);
        file_put_contents(
            $repoRoot . '/tasks/ground-truth/900.json',
            (string) json_encode(['defects' => [
                [
                    'id' => 'd1',
                    'file' => 'src/A.php',
                    'defect_class' => 'sqli',
                    'line' => 10,
                    'span_start' => null,
                    'span_end' => null,
                    'detectability_proof' => 'p',
                ],
            ]]),
        );
        file_put_contents($tasksDir . '/900-valid-findings.json', (string) json_encode([
            'id' => '900-valid-findings',
            'prompt_file' => '900-valid-findings.prompt.md',
            'success_criteria' => [
                ['type' => 'findings_score', 'ground_truth' => '900.json', 'recall_min' => 0.8, 'precision_min' => 0.8],
            ],
        ]));

        $cmd = new ValidateTasksCommand($tasksDir, $repoRoot);
        $this->expectOutputRegex('/OK/');
        $exit = $cmd->run([]);

        self::assertSame(0, $exit);
    }

    public function testFailsOnMalformedGroundTruth(): void
    {
        $tasksDir = $this->tempDir();
        $repoRoot = $this->tempDir();

        file_put_contents($tasksDir . '/901-bad-gt.prompt.md', "Do the thing.\n");
        mkdir($repoRoot . '/tasks/ground-truth', 0o777, true);
        file_put_contents($repoRoot . '/tasks/ground-truth/901.json', '{not valid json');
        file_put_contents($tasksDir . '/901-bad-gt.json', (string) json_encode([
            'id' => '901-bad-gt',
            'prompt_file' => '901-bad-gt.prompt.md',
            'success_criteria' => [
                ['type' => 'findings_score', 'ground_truth' => '901.json', 'recall_min' => 0.8, 'precision_min' => 0.8],
            ],
        ]));

        $cmd = new ValidateTasksCommand($tasksDir, $repoRoot);
        $this->expectOutputRegex('/FAIL/');
        $exit = $cmd->run([]);

        self::assertSame(2, $exit);
    }

    public function testFailsOnMissingRubric(): void
    {
        $tasksDir = $this->tempDir();
        $repoRoot = $this->tempDir();

        file_put_contents($tasksDir . '/902-bad-rubric.prompt.md', "Do the thing.\n");
        file_put_contents($tasksDir . '/902-bad-rubric.json', (string) json_encode([
            'id' => '902-bad-rubric',
            'prompt_file' => '902-bad-rubric.prompt.md',
            'success_criteria' => [
                ['type' => 'rubric_score', 'rubric' => 'nonexistent.json', 'threshold' => 4],
            ],
        ]));

        $cmd = new ValidateTasksCommand($tasksDir, $repoRoot);
        $this->expectOutputRegex('/FAIL/');
        $exit = $cmd->run([]);

        self::assertSame(2, $exit);
    }

    public function testFailsOnMissingPromptFile(): void
    {
        $tasksDir = $this->tempDir();
        $repoRoot = $this->tempDir();

        file_put_contents($tasksDir . '/903-missing-prompt.json', (string) json_encode([
            'id' => '903-missing-prompt',
            'prompt_file' => '903-missing-prompt.prompt.md',
            'success_criteria' => [],
        ]));

        $cmd = new ValidateTasksCommand($tasksDir, $repoRoot);
        $this->expectOutputRegex('/FAIL/');
        $exit = $cmd->run([]);

        self::assertSame(2, $exit);
    }

    public function testVacuousPassOnEmptyTasksDir(): void
    {
        $tasksDir = $this->tempDir();
        $repoRoot = $this->tempDir();

        $cmd = new ValidateTasksCommand($tasksDir, $repoRoot);
        $this->expectOutputRegex('/WARN/');
        $exit = $cmd->run([]);

        self::assertSame(0, $exit);
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/validate-tasks-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o777, true);
        return $dir;
    }
}
