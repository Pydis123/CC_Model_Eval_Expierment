<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Tests\Unit\Dispatch;

use LlmDispatch\Runner\Dispatch\ClaudeCli;
use LlmDispatch\Runner\Dispatch\ClaudeCliResponse;
use LlmDispatch\Runner\Dispatch\DispatchEnvelopeBuilder;
use LlmDispatch\Runner\Dispatch\FailedChecksSummarizer;
use LlmDispatch\Runner\Dispatch\RateLimitInfo;
use LlmDispatch\Runner\Dispatch\RateLimitWaiter;
use LlmDispatch\Runner\Dispatch\RunCoordinator;
use LlmDispatch\Runner\Evaluator\CheckResult;
use LlmDispatch\Runner\Evaluator\EvaluationResult;
use LlmDispatch\Runner\Evaluator\Evaluator;
use LlmDispatch\Runner\Evaluator\EvaluatorInterface;
use PHPUnit\Framework\TestCase;

final class RunCoordinatorTest extends TestCase
{
    private function stubResponse(bool $isError = false, string $model = 'claude-haiku-4-5-20251001'): ClaudeCliResponse
    {
        return new ClaudeCliResponse(
            isError: $isError,
            resultText: 'ok',
            modelIdReported: $model,
            inputTokens: 100,
            outputTokens: 50,
            durationMs: 10_000,
            stopReason: 'end_turn',
            costUsd: 0.01,
            rateLimit: new RateLimitInfo('allowed', null),
            rawStdout: '',
            rawStderr: '',
            exitCode: 0,
        );
    }

    private function blockedResponse(): ClaudeCliResponse
    {
        return new ClaudeCliResponse(
            isError: true,
            resultText: 'rate limited',
            modelIdReported: '',
            inputTokens: 0,
            outputTokens: 0,
            durationMs: 100,
            stopReason: 'error',
            costUsd: 0.0,
            rateLimit: new RateLimitInfo('blocked', 2_000_000_100),
            rawStdout: '',
            rawStderr: '',
            exitCode: 1,
        );
    }

    private function passingEval(): EvaluationResult
    {
        return new EvaluationResult([new CheckResult('phpunit', true, [])], 1.0);
    }

    private function failingEval(): EvaluationResult
    {
        return new EvaluationResult([new CheckResult('phpunit', false, ['error' => 'boom'])], 1.0);
    }

    private function makeEvaluator(EvaluationResult ...$results): EvaluatorInterface
    {
        return new class($results) implements EvaluatorInterface {
            private int $calls = 0;
            /** @param list<EvaluationResult> $results */
            public function __construct(private readonly array $results) {}
            public function evaluate(array $taskDef, string $worktreePath): EvaluationResult
            {
                $r = $this->results[$this->calls] ?? end($this->results);
                $this->calls++;
                return $r;
            }
        };
    }

    /**
     * @param list<ClaudeCliResponse> $responses
     */
    private function makeClaudeCli(array $responses): ClaudeCli
    {
        return new class($responses) implements ClaudeCli {
            private int $index = 0;
            /** @param list<ClaudeCliResponse> $responses */
            public function __construct(private readonly array $responses) {}
            public function dispatch(string $prompt, string $modelId, string $cwd, array $allowedTools): ClaudeCliResponse
            {
                $r = $this->responses[$this->index] ?? end($this->responses);
                $this->index++;
                return $r;
            }
        };
    }

    private function makeCoordinator(ClaudeCli $cli, EvaluatorInterface $evaluator): RunCoordinator
    {
        return new RunCoordinator(
            cli: $cli,
            evaluator: $evaluator,
            envelopeBuilder: new DispatchEnvelopeBuilder(),
            failedChecksSummarizer: new FailedChecksSummarizer(),
            rateLimitWaiter: new RateLimitWaiter(bufferSeconds: 0),
            sleeper: fn(int $s) => null,
            now: fn() => 3_000_000_000,
        );
    }

    public function testFirstIterationPassesReturnsPassedOutcome(): void
    {
        $cli = $this->makeClaudeCli([$this->stubResponse()]);
        $eval = $this->makeEvaluator($this->passingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'claude-haiku-4-5-20251001',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('passed', $outcome->finalOutcome);
        $this->assertSame(1, $outcome->iterationsUsed());
    }

    public function testIterationOneFailsIterationTwoPasses(): void
    {
        $cli = $this->makeClaudeCli([$this->stubResponse(), $this->stubResponse()]);
        $eval = $this->makeEvaluator($this->failingEval(), $this->passingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('passed', $outcome->finalOutcome);
        $this->assertSame(2, $outcome->iterationsUsed());
        $this->assertStringContainsString('Previous attempt failed', $outcome->iterations[1]->promptUsed);
    }

    public function testAllThreeIterationsFailReturnsFailed(): void
    {
        $cli = $this->makeClaudeCli([$this->stubResponse(), $this->stubResponse(), $this->stubResponse()]);
        $eval = $this->makeEvaluator($this->failingEval(), $this->failingEval(), $this->failingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('failed', $outcome->finalOutcome);
        $this->assertSame(3, $outcome->iterationsUsed());
        $this->assertNull($outcome->errorCategory);
    }

    public function testIsErrorAfterRateLimitRetriesReturnsError(): void
    {
        $isError = new ClaudeCliResponse(
            isError: true,
            resultText: 'something broke',
            modelIdReported: '',
            inputTokens: 0, outputTokens: 0, durationMs: 100,
            stopReason: 'error', costUsd: 0.0,
            rateLimit: new RateLimitInfo('allowed', null),
            rawStdout: '', rawStderr: '', exitCode: 1,
        );

        $cli = $this->makeClaudeCli([$isError]);
        $eval = $this->makeEvaluator($this->passingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('error', $outcome->finalOutcome);
        $this->assertSame('claude_cli_is_error', $outcome->errorCategory);
    }

    public function testRateLimitBlockedThenSuccessOnRetryWithinIteration(): void
    {
        $cli = $this->makeClaudeCli([$this->blockedResponse(), $this->stubResponse()]);
        $eval = $this->makeEvaluator($this->passingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('passed', $outcome->finalOutcome);
        $this->assertSame(1, $outcome->iterationsUsed());
    }

    public function testWallClockExceededMidIterationReturnsError(): void
    {
        $longRun = new ClaudeCliResponse(
            isError: false,
            resultText: 'ok',
            modelIdReported: 'm',
            inputTokens: 100, outputTokens: 50,
            durationMs: 2_000_000,
            stopReason: 'end_turn', costUsd: 0.01,
            rateLimit: new RateLimitInfo('allowed', null),
            rawStdout: '', rawStderr: '', exitCode: 0,
        );

        $cli = $this->makeClaudeCli([$longRun]);
        $eval = $this->makeEvaluator($this->failingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('error', $outcome->finalOutcome);
        $this->assertSame('wall_clock_exceeded', $outcome->errorCategory);
    }

    public function testMaxRateLimitRetriesExceededReturnsError(): void
    {
        $cli = $this->makeClaudeCli([
            $this->blockedResponse(), $this->blockedResponse(),
            $this->blockedResponse(), $this->blockedResponse(),
        ]);
        $eval = $this->makeEvaluator($this->passingEval());

        $outcome = $this->makeCoordinator($cli, $eval)->execute(
            rawPrompt: 'do it',
            taskDef: ['success_criteria' => []],
            worktreePath: '/tmp/wt',
            modelId: 'm',
            allowedTools: ['Bash'],
            maxIterations: 3,
            maxWallClockS: 1800,
        );

        $this->assertSame('error', $outcome->finalOutcome);
        $this->assertSame('claude_cli_is_error', $outcome->errorCategory);
    }
}
