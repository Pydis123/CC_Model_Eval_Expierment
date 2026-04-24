<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Evaluator\Check;

use LlmDispatch\Runner\Evaluator\CheckInterface;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Support\ProcessExecutor;
use RuntimeException;

final class QueryCountCheck implements CheckInterface
{
    private readonly ProcessExecutor $executor;
    private readonly string $cliBinary;
    private readonly string $phpBinary;

    public function __construct(
        private readonly string $route,
        private readonly int $max,
        private readonly bool $authAsAdmin = true,
        ?ProcessExecutor $executor = null,
        ?string $cliBinary = null,
        ?string $phpBinary = null,
    ) {
        $this->executor = $executor ?? new ProcessExecutor();
        $this->cliBinary = $cliBinary ?? (string) realpath(__DIR__ . '/../../../bin/cli');
        $this->phpBinary = $phpBinary ?? 'php';
    }

    public function run(string $worktreePath): CheckResult
    {
        $command = [
            $this->phpBinary,
            $this->cliBinary,
            'probe',
            'query-count',
            '--worktree=' . $worktreePath,
            '--route=' . $this->route,
        ];

        if ($this->authAsAdmin) {
            $command[] = '--auth-as-admin';
        }

        $result = $this->executor->exec($worktreePath, $command);

        if ($result->exitCode !== 0) {
            throw new RuntimeException(
                'query-count probe failed (exit ' . $result->exitCode . '): ' . $result->stderr
            );
        }

        /** @var array{route: string, query_count: int} $data */
        $data = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
        $actual = (int) $data['query_count'];

        return new CheckResult(
            type: 'query_count',
            passed: $actual <= $this->max,
            details: [
                'route' => $this->route,
                'max' => $this->max,
                'actual' => $actual,
            ],
        );
    }
}
