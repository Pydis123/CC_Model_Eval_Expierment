<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Report;

use LlmDispatch\Runner\Analysis\CellStats;
use LlmDispatch\Runner\Analysis\PolicyBResult;
use LlmDispatch\Runner\Analysis\PolicyBSimulation;
use LlmDispatch\Runner\Config;

final class FindingsWriter
{
    /**
     * @param array<string, array<string, CellStats>> $matrix
     */
    public function render(
        array $matrix,
        PolicyBSimulation $simulation,
        Config $config,
        string $sourcePath,
        int $rowCount,
        string $generatedAt,
    ): string {
        $md = new MarkdownBuilder();

        $md->h1('LLM Dispatch Experiment — Findings');
        $md->paragraph(sprintf(
            "**Generated:** %s  \n**Source:** %s (%d rows)  \n**Bootstrap:** %d samples, seed=%d",
            $generatedAt,
            $sourcePath,
            $rowCount,
            $simulation->bootstrapSamples,
            $simulation->bootstrapSeed,
        ));

        $this->appendSummary($md, $simulation);
        $this->appendPerTaskResults($md, $matrix, $config);
        $this->appendPolicyB($md, $simulation, $config);
        $this->appendReproducibility($md);

        return $md->build();
    }

    private function appendSummary(MarkdownBuilder $md, PolicyBSimulation $sim): void
    {
        $md->h2('Summary');
        $md->paragraph(sprintf(
            'Across %d tasks and 3 model tiers (haiku, sonnet, opus), Policy B (cheapest-first escalation) is estimated to cost %s tokens (95%% CI: %s–%s) and %s seconds (95%% CI: %s–%s) per experiment run. Probability that all three tiers fail on a given task: %.2f%%.',
            count($sim->perTask),
            $this->fmt($sim->overall->expectedTokens),
            $this->fmt($sim->overall->ciLowTokens),
            $this->fmt($sim->overall->ciHighTokens),
            $this->fmt($sim->overall->expectedWallClockS),
            $this->fmt($sim->overall->ciLowWallClockS),
            $this->fmt($sim->overall->ciHighWallClockS),
            $sim->overall->maxTierFailRate * 100,
        ));
    }

    /**
     * @param array<string, array<string, CellStats>> $matrix
     */
    private function appendPerTaskResults(MarkdownBuilder $md, array $matrix, Config $config): void
    {
        $md->h2('Per-task results (Policy A — retry-only)');

        foreach ($config->taskIds as $taskId) {
            $md->h3($taskId);
            $rows = [];
            foreach ($config->tiers as $tier) {
                $stats = $matrix[$taskId][$tier];
                $rows[] = [
                    $tier,
                    sprintf('%d/%d', $stats->nPassed, $stats->nRuns),
                    $this->fmt($stats->meanTokens),
                    $this->fmt($stats->meanWallClockS),
                    number_format($stats->meanIterations, 2),
                ];
            }
            $md->table(
                headers: ['Tier', 'Pass rate', 'Mean tokens', 'Mean wall-clock (s)', 'Mean iterations'],
                rows: $rows,
            );
        }
    }

    private function appendPolicyB(MarkdownBuilder $md, PolicyBSimulation $sim, Config $config): void
    {
        $md->h2('Policy B simulation (cheapest-first escalation)');

        $md->h3('Per-task expected cost');
        $rows = [];
        foreach ($config->taskIds as $taskId) {
            $rows[] = $this->policyBRow($taskId, $sim->perTask[$taskId]);
        }
        $md->table(
            headers: ['Task', 'Expected tokens', '95% CI', 'Expected time (s)', '95% CI', 'P(max_tier_failed)'],
            rows: $rows,
        );

        $md->h3('Experiment-wide totals');
        $md->table(
            headers: ['Metric', 'Mean', '95% CI'],
            rows: [
                [
                    'Total tokens',
                    $this->fmt($sim->overall->expectedTokens),
                    sprintf('[%s, %s]', $this->fmt($sim->overall->ciLowTokens), $this->fmt($sim->overall->ciHighTokens)),
                ],
                [
                    'Total wall-clock (s)',
                    $this->fmt($sim->overall->expectedWallClockS),
                    sprintf('[%s, %s]', $this->fmt($sim->overall->ciLowWallClockS), $this->fmt($sim->overall->ciHighWallClockS)),
                ],
                [
                    'Tasks failed (avg)',
                    number_format($sim->overall->maxTierFailRate * count($sim->perTask), 2),
                    '',
                ],
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function policyBRow(string $taskId, PolicyBResult $r): array
    {
        return [
            $taskId,
            $this->fmt($r->expectedTokens),
            sprintf('[%s, %s]', $this->fmt($r->ciLowTokens), $this->fmt($r->ciHighTokens)),
            $this->fmt($r->expectedWallClockS),
            sprintf('[%s, %s]', $this->fmt($r->ciLowWallClockS), $this->fmt($r->ciHighWallClockS)),
            sprintf('%.2f', $r->maxTierFailRate),
        ];
    }

    private function appendReproducibility(MarkdownBuilder $md): void
    {
        $md->h2('Reproducibility');
        $md->paragraph(
            'This file is regenerated deterministically by `runner/bin/cli report`. Same input + same seed = identical output. Verify by:'
        );
        $md->fencedCode(
            'bash',
            "runner/bin/cli report --output=/tmp/findings.md\ndiff docs/findings.md /tmp/findings.md\n",
        );
    }

    private function fmt(float $n): string
    {
        return number_format($n, 0, '.', ',');
    }
}
