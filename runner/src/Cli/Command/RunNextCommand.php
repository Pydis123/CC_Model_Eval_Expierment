<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use DateTimeImmutable;
use DateTimeZone;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Dispatch\DispatchDisposition;
use LlmDispatch\Runner\Dispatch\RunCoordinator;
use LlmDispatch\Runner\Dispatch\RunOutcome;
use LlmDispatch\Runner\Execution\TaskPromptLoader;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\StateManager;
use LlmDispatch\Runner\Worktree\WorktreeManager;

final class RunNextCommand implements CommandInterface
{
    /**
     * @param list<string>      $allowedTools
     * @param callable(): string $now   returns ISO8601 UTC
     */
    public function __construct(
        private readonly StateManager $stateManager,
        private readonly ResultsLogger $resultsLogger,
        private readonly TaskPromptLoader $taskPromptLoader,
        private readonly WorktreeManager $worktreeManager,
        private readonly RunCoordinator $coordinator,
        private readonly array $allowedTools,
        private $now,
        private readonly string $claudeVersion = '',
    ) {}

    public function run(array $args): int
    {
        $runStartIso = ($this->now)();

        $claimed = $this->stateManager->claimNext($runStartIso);
        if ($claimed === null) {
            fwrite(STDERR, "No remaining runs.\n");
            return 1;
        }

        $state = $this->stateManager->load();
        $modelId = $state->pinnedModels[$claimed->modelTier] ?? null;
        if ($modelId === null) {
            fwrite(STDERR, "No pinned model for tier: {$claimed->modelTier}\n");
            return 4;
        }

        $task = $this->taskPromptLoader->load($claimed->taskId);

        $worktreePath = $this->worktreeManager->prepare($claimed->runId);

        $outcome = $this->coordinator->execute(
            rawPrompt: $task->prompt,
            taskDef: $task->taskDef,
            worktreePath: $worktreePath,
            modelId: $modelId,
            allowedTools: $this->allowedTools,
            maxIterations: $task->maxIterations,
            maxWallClockS: $task->maxWallClockS,
        );

        $runEndIso = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        $this->resultsLogger->append($this->buildRow($claimed, $outcome, $modelId, $runStartIso, $runEndIso));

        $this->stateManager->save($this->stateManager->load()->moveToCompleted($claimed->runId));

        $this->worktreeManager->cleanup($claimed->runId, $worktreePath, $outcome->finalOutcome === 'passed');

        if ($outcome->finalOutcome === 'error') {
            return 3;
        }
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(Run $run, RunOutcome $outcome, string $modelId, string $startIso, string $endIso): array
    {
        $iterations = [];
        foreach ($outcome->iterations as $it) {
            $iterations[] = [
                'index' => $it->index,
                'tokens_in' => $it->tokensIn,
                'tokens_out' => $it->tokensOut,
                'wall_clock_s' => $it->wallClockS,
                'model_id_reported' => $it->modelIdReported,
                'cost_usd' => $it->costUsd,
                'evaluator_outcome' => $it->evaluatorOutcome(),
                'error_category' => $it->errorCategory,
                'result_text' => mb_substr($it->resultText, 0, 500),
            ];
        }

        $totalS = (new DateTimeImmutable($endIso))->getTimestamp() - (new DateTimeImmutable($startIso))->getTimestamp();

        return [
            'run_id' => $run->runId,
            'task_id' => $run->taskId,
            'model_tier' => $run->modelTier,
            'model_id' => $modelId,
            'n' => $run->n,
            'outcome' => $outcome->finalOutcome === 'error' ? 'failed' : $outcome->finalOutcome,
            'iterations_used' => $outcome->iterationsUsed(),
            'iterations' => $iterations,
            'tokens_subagent_in' => $outcome->totalTokensIn(),
            'tokens_subagent_out' => $outcome->totalTokensOut(),
            'tokens_pm_overhead' => 0,
            'wall_clock_subagent_s' => $outcome->totalWallClockS(),
            'wall_clock_total_s' => $totalS,
            'timestamp_start' => $startIso,
            'timestamp_end' => $endIso,
            'evaluator_details' => $outcome->lastEvaluation()?->toArray() ?? ['outcome' => 'error', 'checks' => []],
            'dispatch_disposition' => DispatchDisposition::classify($modelId, $outcome),
            'claude_cli_version' => $this->claudeVersion,
            'error_category' => $outcome->errorCategory,
        ];
    }
}
