<?php

declare(strict_types=1);

namespace LlmDispatch\Runner\Report;

use LlmDispatch\Runner\Analysis\CellStats;
use LlmDispatch\Runner\Analysis\MetricCi;
use LlmDispatch\Runner\Analysis\PolicyBResult;
use LlmDispatch\Runner\Analysis\PolicyBSimulation;
use LlmDispatch\Runner\Config;

final class FindingsWriter
{
    /**
     * @param array<string, array<string, CellStats>> $matrix
     * @param array<string, array<string, array<string, int>>>|null $dispositionTally
     */
    public function render(
        array $matrix,
        PolicyBSimulation $simulation,
        Config $config,
        string $sourcePath,
        int $rowCount,
        string $generatedAt,
        ?array $dispositionTally = null,
    ): string {
        $md = new MarkdownBuilder();

        $md->h1('LLM Dispatch Experiment — Findings');
        $md->paragraph(sprintf(
            "**Generated:** %s  \n**Source:** %s (%d rows)  \n**Bootstrap:** %d samples, seed=%d",
            $generatedAt,
            basename($sourcePath),
            $rowCount,
            $simulation->bootstrapSamples,
            $simulation->bootstrapSeed,
        ));

        $this->appendSummary($md, $simulation, $config);
        $this->appendPerTaskResults($md, $matrix, $config);
        $this->appendPolicyB($md, $simulation, $config);
        $this->appendPhase2Metrics($md, $matrix, $config);
        $this->appendSafeguardInterference($md, $dispositionTally);
        $this->appendReproducibility($md);

        return $md->build();
    }

    private function appendSummary(MarkdownBuilder $md, PolicyBSimulation $sim, Config $config): void
    {
        $tierList = implode(', ', $config->tiers);
        $md->h2('Summary');
        $md->paragraph(sprintf(
            'Across %d tasks and %d model tiers (%s), Policy B (cheapest-first escalation) is estimated to cost %s tokens (95%% CI: %s–%s) and %s seconds (95%% CI: %s–%s) per experiment run. Probability that all tiers fail on a given task: %.2f%%.',
            count($sim->perTask),
            count($config->tiers),
            $tierList,
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

    /** @param array<string, array<string, CellStats>> $matrix */
    private function appendPhase2Metrics(MarkdownBuilder $md, array $matrix, Config $config): void
    {
        $rows = [];
        foreach ($config->taskIds as $taskId) {
            foreach ($config->tiers as $tier) {
                $s = $matrix[$taskId][$tier];
                if ($s->meanRecall === null && $s->meanRubricTotal === null) {
                    continue;
                }
                $rows[] = [
                    $taskId,
                    $tier,
                    $this->fmtMetric($s->meanRecall, $s->metricValues('recall'), $config),
                    $this->fmtMetric($s->meanPrecisionAdjusted, $s->metricValues('precision_adjusted'), $config),
                    $this->fmtMetric($s->meanRubricTotal, $s->metricValues('rubric_total'), $config),
                ];
            }
        }
        if ($rows === []) {
            return;
        }
        $md->h2('Findings/rubric metrics (Phase 2 categories)');
        $md->table(
            headers: ['Task', 'Tier', 'Recall (95% CI)', 'Precision adj. (95% CI)', 'Rubric total (95% CI)'],
            rows: $rows,
        );
    }

    /** @param list<float>|null $values */
    private function fmtMetric(?float $mean, ?array $values, Config $config): string
    {
        if ($mean === null || $values === null) {
            return '—';
        }
        $ci = MetricCi::bootstrap($values, 1000, $config->planSeed);
        return sprintf('%.2f [%.2f, %.2f]', $mean, $ci['low'], $ci['high']);
    }

    /** @param array<string, array<string, array<string, int>>>|null $tally */
    private function appendSafeguardInterference(MarkdownBuilder $md, ?array $tally): void
    {
        if ($tally === null) {
            return;
        }
        $md->h2('Safeguard interference');
        $rows = [];
        foreach ($tally as $taskId => $byTier) {
            foreach ($byTier as $tier => $counts) {
                $refused = $counts['refused_in_band'] ?? 0;
                $rerouted = $counts['model_rerouted'] ?? 0;
                if ($refused === 0 && $rerouted === 0) {
                    continue;
                }
                $rows[] = [$taskId, $tier, (string) $refused, (string) $rerouted, (string) array_sum($counts)];
            }
        }
        if ($rows === []) {
            $md->paragraph('No in-band refusals or model reroutes recorded across any attempt.');
            return;
        }
        $md->table(
            headers: ['Task', 'Tier', 'Refused (in-band)', 'Rerouted', 'Total attempts'],
            rows: $rows,
        );
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
