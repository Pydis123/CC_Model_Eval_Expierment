<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Dispatch;

use LlmDispatch\Runner\Evaluator\EvaluatorInterface;

final class RunCoordinator
{
    private const MAX_RATE_LIMIT_RETRIES_PER_ITERATION = 3;

    /**
     * @param callable(int): void $sleeper
     * @param callable(): int     $now
     */
    public function __construct(
        private readonly ClaudeCli $cli,
        private readonly EvaluatorInterface $evaluator,
        private readonly DispatchEnvelopeBuilder $envelopeBuilder,
        private readonly FailedChecksSummarizer $failedChecksSummarizer,
        private readonly RateLimitWaiter $rateLimitWaiter,
        private $sleeper,
        private $now,
    ) {}

    /**
     * @param array{success_criteria: list<array<string, mixed>>} $taskDef
     * @param list<string> $allowedTools
     */
    public function execute(
        string $rawPrompt,
        array $taskDef,
        string $worktreePath,
        string $modelId,
        array $allowedTools,
        int $maxIterations,
        int $maxWallClockS,
    ): RunOutcome {
        $iterations = [];
        $priorFailed = null;
        $elapsedS = 0;

        $subagentCwd = $worktreePath . '/mock-project';

        for ($i = 1; $i <= $maxIterations; $i++) {
            $prompt = $this->envelopeBuilder->build($rawPrompt, $priorFailed);

            [$response, $rateLimitIterations] = $this->dispatchWithRateLimitRetries($prompt, $modelId, $subagentCwd, $allowedTools);

            if ($response === null) {
                $iterations[] = new IterationOutcome(
                    index: $i,
                    promptUsed: $prompt,
                    tokensIn: 0,
                    tokensOut: 0,
                    wallClockS: 0,
                    modelIdReported: '',
                    costUsd: 0.0,
                    evaluation: null,
                    errorCategory: 'claude_cli_is_error',
                );
                return new RunOutcome($iterations, 'error', 'claude_cli_is_error');
            }

            if ($response->isError) {
                $iterations[] = new IterationOutcome(
                    index: $i,
                    promptUsed: $prompt,
                    tokensIn: $response->inputTokens,
                    tokensOut: $response->outputTokens,
                    wallClockS: (int) round($response->durationSeconds()),
                    modelIdReported: $response->modelIdReported,
                    costUsd: $response->costUsd,
                    evaluation: null,
                    errorCategory: 'claude_cli_is_error',
                    resultText: $response->resultText,
                );
                return new RunOutcome($iterations, 'error', 'claude_cli_is_error');
            }

            $iterationWallS = (int) round($response->durationSeconds());
            $elapsedS += $iterationWallS;
            if ($elapsedS > $maxWallClockS) {
                $iterations[] = new IterationOutcome(
                    index: $i,
                    promptUsed: $prompt,
                    tokensIn: $response->inputTokens,
                    tokensOut: $response->outputTokens,
                    wallClockS: $iterationWallS,
                    modelIdReported: $response->modelIdReported,
                    costUsd: $response->costUsd,
                    evaluation: null,
                    errorCategory: 'wall_clock_exceeded',
                    resultText: $response->resultText,
                );
                return new RunOutcome($iterations, 'error', 'wall_clock_exceeded');
            }

            $evaluation = $this->evaluator->evaluate($taskDef, $worktreePath);

            $iterations[] = new IterationOutcome(
                index: $i,
                promptUsed: $prompt,
                tokensIn: $response->inputTokens,
                tokensOut: $response->outputTokens,
                wallClockS: $iterationWallS,
                modelIdReported: $response->modelIdReported,
                costUsd: $response->costUsd,
                evaluation: $evaluation,
                errorCategory: null,
                resultText: $response->resultText,
            );

            if ($evaluation->outcome === 'passed') {
                return new RunOutcome($iterations, 'passed', null);
            }

            $priorFailed = $this->failedChecksSummarizer->summarize($evaluation);
        }

        return new RunOutcome($iterations, 'failed', null);
    }

    /**
     * @param list<string> $allowedTools
     * @return array{0: ?ClaudeCliResponse, 1: int}
     */
    private function dispatchWithRateLimitRetries(
        string $prompt,
        string $modelId,
        string $worktreePath,
        array $allowedTools,
    ): array {
        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES_PER_ITERATION; $attempt++) {
            $response = $this->cli->dispatch($prompt, $modelId, $worktreePath, $allowedTools);

            if (!$response->rateLimit->isBlocked()) {
                return [$response, $attempt];
            }

            $sleepSeconds = $this->rateLimitWaiter->computeSleepSeconds(
                $response->rateLimit,
                ($this->now)(),
            );
            ($this->sleeper)($sleepSeconds);
        }

        return [null, self::MAX_RATE_LIMIT_RETRIES_PER_ITERATION];
    }
}
