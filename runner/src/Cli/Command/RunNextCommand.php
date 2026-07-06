<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Cli\Command;

use DateTimeImmutable;
use DateTimeZone;
use LlmDispatch\Runner\Analysis\ContaminationDetector;
use LlmDispatch\Runner\Cli\CommandInterface;
use LlmDispatch\Runner\Dispatch\DispatchDisposition;
use LlmDispatch\Runner\Dispatch\IterationOutcome;
use LlmDispatch\Runner\Dispatch\RunCoordinator;
use LlmDispatch\Runner\Dispatch\RunOutcome;
use LlmDispatch\Runner\Execution\TaskPromptLoader;
use LlmDispatch\Runner\Judge\JudgeClient;
use LlmDispatch\Runner\Logging\ResultsLogger;
use LlmDispatch\Runner\State\Run;
use LlmDispatch\Runner\State\StateManager;
use LlmDispatch\Runner\Worktree\ExportWorktreeManager;
use LlmDispatch\Runner\Worktree\WorktreeManager;
use RuntimeException;

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
        private readonly ?ExportWorktreeManager $exportWorktreeManager = null,
        private readonly ?JudgeClient $judgeClient = null,
        private readonly ?ContaminationDetector $contaminationDetector = null,
        private readonly string $contaminatedDir = '',
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

        $exportRef = $task->taskDef['export_ref'] ?? null;
        if ($exportRef !== null && $this->exportWorktreeManager === null) {
            fwrite(STDERR, "Task requires export isolation but no ExportWorktreeManager is wired.\n");
            return 4;
        }
        $manager = $exportRef !== null ? $this->exportWorktreeManager : $this->worktreeManager;
        $worktreePath = $exportRef !== null
            ? $this->exportWorktreeManager->prepareExport($claimed->runId, (string) $exportRef, $claimed->taskId)
            : $this->worktreeManager->prepare($claimed->runId);

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

        $contamination = $this->scanContamination($outcome);
        $disposition = $this->classifyDisposition($modelId, $outcome, $contamination);

        $this->resultsLogger->append(
            $this->buildRow($claimed, $outcome, $modelId, $runStartIso, $runEndIso, $contamination, $disposition),
        );

        if ($disposition === DispatchDisposition::CONTAMINATED) {
            $this->persistContaminatedTranscript($claimed->runId, $outcome);
        }

        $this->stateManager->save($this->stateManager->load()->moveToCompleted($claimed->runId));

        $passed = $outcome->finalOutcome === 'passed' && $disposition !== DispatchDisposition::CONTAMINATED;
        $manager->cleanup($claimed->runId, $worktreePath, $passed);

        if ($outcome->finalOutcome === 'error') {
            return 3;
        }
        return 0;
    }

    private function persistContaminatedTranscript(string $runId, RunOutcome $outcome): void
    {
        if ($this->contaminatedDir === '') {
            return;
        }

        if (!is_dir($this->contaminatedDir)) {
            mkdir($this->contaminatedDir, 0777, true);
        }

        $transcript = implode("\n", array_map(
            static fn(IterationOutcome $it): string => $it->transcript,
            $outcome->iterations,
        ));

        file_put_contents($this->contaminatedDir . '/' . $runId . '.log', $transcript);
    }

    /**
     * @param array{contaminated: bool, matches: list<string>, evidence: list<string>} $contamination
     * @return array<string, mixed>
     */
    private function buildRow(
        Run $run,
        RunOutcome $outcome,
        string $modelId,
        string $startIso,
        string $endIso,
        array $contamination,
        string $disposition,
    ): array {
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

        $row = [
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
            'metrics' => $outcome->lastEvaluation()?->metrics(),
            'dispatch_disposition' => $disposition,
            'claude_cli_version' => $this->claudeVersion,
            'error_category' => $outcome->errorCategory,
        ];

        if ($disposition === DispatchDisposition::CONTAMINATED) {
            $row['contamination_matches'] = $contamination['matches'];
            $row['contamination_evidence'] = $contamination['evidence'];
        }

        return $row;
    }

    /**
     * @param array{contaminated: bool, matches: list<string>, evidence: list<string>} $contamination
     */
    private function classifyDisposition(string $modelId, RunOutcome $outcome, array $contamination): string
    {
        if ($contamination['contaminated']) {
            return DispatchDisposition::CONTAMINATED;
        }

        $disposition = DispatchDisposition::classify($modelId, $outcome);
        if ($disposition !== DispatchDisposition::COMPLETED || !$this->artifactMissing($outcome)) {
            return $disposition;
        }
        if ($this->judgeClient === null) {
            return $disposition;
        }
        $last = $outcome->iterations[count($outcome->iterations) - 1] ?? null;
        try {
            $verdict = $this->judgeClient->judgeJson(sprintf(
                "An agent was asked to produce a findings/decision artifact and produced none.\n"
                . "Its final message follows. Did it refuse the task, or attempt it and fail?\n"
                . "MESSAGE:\n%s\n"
                . 'Reply ONLY with JSON: {"verdict": "refusal" | "attempted"}',
                mb_substr($last?->resultText ?? '', 0, 2000),
            ));
        } catch (RuntimeException) {
            return $disposition;
        }
        return ($verdict['verdict'] ?? '') === 'refusal' ? DispatchDisposition::REFUSED : $disposition;
    }

    /**
     * @return array{contaminated: bool, matches: list<string>, evidence: list<string>}
     */
    private function scanContamination(RunOutcome $outcome): array
    {
        if ($this->contaminationDetector === null) {
            return ['contaminated' => false, 'matches' => [], 'evidence' => []];
        }

        $transcript = implode("\n", array_map(
            static fn(IterationOutcome $it): string => $it->transcript,
            $outcome->iterations,
        ));

        return $this->contaminationDetector->scan($transcript);
    }

    private function artifactMissing(RunOutcome $outcome): bool
    {
        foreach ($outcome->lastEvaluation()?->checks ?? [] as $check) {
            if (($check->details['artifact_missing'] ?? false) === true) {
                return true;
            }
        }
        return false;
    }
}
