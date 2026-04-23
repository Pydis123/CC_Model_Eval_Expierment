<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Evaluator\Evaluator;

final class EvaluateCommand implements CommandInterface
{
    public function __construct(
        private readonly string $tasksDir,
        private readonly Evaluator $evaluator,
    ) {}

    public function run(array $args): int
    {
        $taskId = $this->argValue($args, '--task=');
        $worktree = $this->argValue($args, '--worktree=');

        if ($taskId === null || $worktree === null) {
            fwrite(STDERR, "Required: --task=<id> --worktree=<path>\n");
            return 2;
        }

        $taskFile = $this->tasksDir . '/' . $taskId . '.json';
        if (!is_file($taskFile)) {
            fwrite(STDERR, "Task file missing: {$taskFile}\n");
            return 3;
        }

        /** @var array<string, mixed> $taskDef */
        $taskDef = json_decode((string) file_get_contents($taskFile), true, 512, JSON_THROW_ON_ERROR);

        $result = $this->evaluator->evaluate($taskDef, $worktree);

        echo json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";

        return $result->outcome === 'passed' ? 0 : 1;
    }

    /**
     * @param list<string> $args
     */
    private function argValue(array $args, string $prefix): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return null;
    }
}
