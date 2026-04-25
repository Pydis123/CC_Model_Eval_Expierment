#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cost calculator — forecast monthly token spend under different tier
 * strategies, given a task-type mix and a dispatch volume.
 *
 * Uses experiment data (results/results.jsonl) to compute per-(category, tier)
 * mean cost and pass rate, then projects to a hypothetical workload.
 *
 * Usage:
 *   php runner/bin/cost-calculator.php \
 *     --dispatches-per-month=200 \
 *     --mix=trivial:0.3,crud:0.2,migration:0.15,refactor:0.1,bugfix:0.15,frontend:0.1
 *
 *   php runner/bin/cost-calculator.php   # uses experiment-default mix (1/8 each)
 */

const REPO_ROOT = __DIR__ . '/../..';
const RESULTS_PATH = REPO_ROOT . '/results/results.jsonl';

const CATEGORY_TO_TASK_ID = [
    'trivial'   => '001-i18n-status-flik',
    'crud'      => '002-crud-ticket-tag',
    'query'     => '003-n-plus-one-fix',
    'migration' => '004-sla-deadline-migration',
    'refactor'  => '005-state-service-refactor',
    'bugfix'    => '006-intermittent-test-bugfix',
    'rbac'      => '007-batch-close-rbac',
    'frontend'  => '008-comment-composer-alpine',
];

const TIERS = ['haiku', 'sonnet', 'opus'];

/**
 * @return array<string, float|string|int>
 */
function parseArgs(array $argv): array
{
    $args = [
        'dispatches' => 100,
        'mix' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--dispatches-per-month=')) {
            $args['dispatches'] = (int) substr($arg, 23);
        } elseif (str_starts_with($arg, '--mix=')) {
            $args['mix'] = substr($arg, 6);
        } elseif ($arg === '-h' || $arg === '--help') {
            $args['help'] = true;
        }
    }

    return $args;
}

function printHelp(): void
{
    echo <<<HELP
Usage: php runner/bin/cost-calculator.php [options]

Options:
  --dispatches-per-month=N    Total dispatches/month (default: 100)
  --mix=cat1:w1,cat2:w2,...   Task-type weights (normalized to 1)

Available categories: trivial, crud, query, migration, refactor, bugfix,
                      rbac, frontend

Default mix is uniform (1/8 each).

Output: expected monthly tokens and wall-clock under five strategies:
  - All-Haiku
  - All-Sonnet
  - All-Opus
  - Policy B (Haiku → Opus, skip Sonnet)
  - Policy B-3 (Haiku → Sonnet → Opus)

Pass rates are derived from the experiment's recorded outcomes (N=3 per
cell). Treat output as order-of-magnitude — see docs/limitations.md.

HELP;
}

/**
 * Parse a mix spec like "trivial:0.3,crud:0.2" into normalized weights.
 *
 * @return array<string, float>
 */
function parseMix(?string $spec): array
{
    if ($spec === null) {
        $uniform = 1.0 / count(CATEGORY_TO_TASK_ID);
        return array_fill_keys(array_keys(CATEGORY_TO_TASK_ID), $uniform);
    }

    $weights = [];
    foreach (explode(',', $spec) as $pair) {
        [$cat, $w] = explode(':', $pair);
        $cat = trim($cat);
        if (!isset(CATEGORY_TO_TASK_ID[$cat])) {
            fwrite(STDERR, "Unknown category: {$cat}\n");
            fwrite(STDERR, "Known: " . implode(', ', array_keys(CATEGORY_TO_TASK_ID)) . "\n");
            exit(2);
        }
        $weights[$cat] = (float) trim($w);
    }

    $total = array_sum($weights);
    if ($total <= 0) {
        fwrite(STDERR, "Mix weights must sum to > 0\n");
        exit(2);
    }

    foreach ($weights as $cat => $w) {
        $weights[$cat] = $w / $total;
    }

    foreach (array_keys(CATEGORY_TO_TASK_ID) as $cat) {
        $weights[$cat] = $weights[$cat] ?? 0.0;
    }

    return $weights;
}

/**
 * Load per-cell statistics from results.jsonl.
 *
 * @return array<string, array<string, array{tokens: float, wall: float, pass_rate: float}>>
 */
function loadCellStats(): array
{
    if (!is_file(RESULTS_PATH)) {
        fwrite(STDERR, "results.jsonl not found: " . RESULTS_PATH . "\n");
        exit(1);
    }

    $cells = [];
    foreach (file(RESULTS_PATH) as $line) {
        $r = json_decode($line, true);
        if (!is_array($r)) continue;
        $key = $r['task_id'] . '|' . $r['model_tier'];
        $cells[$key][] = $r;
    }

    $stats = [];
    foreach ($cells as $key => $rows) {
        [$task, $tier] = explode('|', $key);
        $n = count($rows);
        $tokens = array_sum(array_map(
            fn($r) => $r['tokens_subagent_in'] + $r['tokens_subagent_out'],
            $rows,
        )) / $n;
        $wall = array_sum(array_map(fn($r) => $r['wall_clock_subagent_s'], $rows)) / $n;
        $passed = count(array_filter($rows, fn($r) => $r['outcome'] === 'passed'));
        $stats[$task][$tier] = [
            'tokens' => $tokens,
            'wall' => $wall,
            'pass_rate' => $passed / $n,
        ];
    }

    return $stats;
}

/**
 * Expected cost of one dispatch on a single tier (no escalation).
 *
 * @return array{tokens: float, wall: float, pass_rate: float}
 */
function singleTierCost(array $stats, string $taskId, string $tier): array
{
    return $stats[$taskId][$tier];
}

/**
 * Expected cost of a tiered escalation chain. If a tier fails, we pay its
 * full cost and then dispatch the next tier in the chain.
 *
 * @param list<string> $chain
 * @return array{tokens: float, wall: float, pass_rate: float}
 */
function escalationCost(array $stats, string $taskId, array $chain): array
{
    $expectedTokens = 0.0;
    $expectedWall = 0.0;
    $reachedSurvival = 1.0;
    $finalPass = 0.0;

    foreach ($chain as $tier) {
        $cell = $stats[$taskId][$tier];
        $expectedTokens += $reachedSurvival * $cell['tokens'];
        $expectedWall += $reachedSurvival * $cell['wall'];
        $finalPass += $reachedSurvival * $cell['pass_rate'];
        $reachedSurvival *= (1.0 - $cell['pass_rate']);
    }

    return [
        'tokens' => $expectedTokens,
        'wall' => $expectedWall,
        'pass_rate' => $finalPass,
    ];
}

/**
 * Project per-dispatch cost across a workload mix.
 *
 * @param array<string, float> $mix  category => weight (normalized)
 * @return array{tokens: float, wall: float, pass_rate: float}
 */
function projectCost(array $stats, array $mix, callable $perTaskCost): array
{
    $tokens = 0.0;
    $wall = 0.0;
    $pass = 0.0;
    foreach ($mix as $cat => $weight) {
        if ($weight <= 0) continue;
        $taskId = CATEGORY_TO_TASK_ID[$cat];
        $cell = $perTaskCost($taskId);
        $tokens += $weight * $cell['tokens'];
        $wall += $weight * $cell['wall'];
        $pass += $weight * $cell['pass_rate'];
    }
    return ['tokens' => $tokens, 'wall' => $wall, 'pass_rate' => $pass];
}

function fmt(float $n): string
{
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 2) . 'M';
    if ($n >= 1_000) return number_format($n / 1_000, 1) . 'k';
    return number_format($n, 0);
}

function main(array $argv): int
{
    $args = parseArgs($argv);
    if ($args['help'] ?? false) {
        printHelp();
        return 0;
    }

    $stats = loadCellStats();
    $mix = parseMix($args['mix']);
    $dispatches = $args['dispatches'];

    $strategies = [
        'All-Haiku'     => fn($t) => singleTierCost($stats, $t, 'haiku'),
        'All-Sonnet'    => fn($t) => singleTierCost($stats, $t, 'sonnet'),
        'All-Opus'      => fn($t) => singleTierCost($stats, $t, 'opus'),
        'Haiku → Opus'  => fn($t) => escalationCost($stats, $t, ['haiku', 'opus']),
        'Haiku → Sonnet → Opus' => fn($t) => escalationCost($stats, $t, ['haiku', 'sonnet', 'opus']),
    ];

    echo "\n";
    echo "Dispatches per month: {$dispatches}\n";
    echo "Workload mix:\n";
    foreach ($mix as $cat => $w) {
        if ($w > 0) {
            printf("  %-10s %5.1f%%\n", $cat, $w * 100);
        }
    }
    echo "\n";

    printf("%-25s | %12s | %12s | %12s\n", 'Strategy', 'Tokens/mo', 'Wall-clock/mo', 'Pass rate');
    echo str_repeat('-', 75) . "\n";

    foreach ($strategies as $name => $fn) {
        $perDispatch = projectCost($stats, $mix, $fn);
        $monthlyTokens = $perDispatch['tokens'] * $dispatches;
        $monthlyWall = $perDispatch['wall'] * $dispatches;
        $hours = $monthlyWall / 3600;
        printf(
            "%-25s | %12s | %8.1f hrs | %11.1f%%\n",
            $name,
            fmt($monthlyTokens),
            $hours,
            $perDispatch['pass_rate'] * 100,
        );
    }

    echo "\n";
    echo "Notes:\n";
    echo "- 'Tokens/mo' is subagent input + output, no PM overhead.\n";
    echo "- 'Wall-clock/mo' is sequential subagent time. Parallel dispatch reduces this.\n";
    echo "- 'Pass rate' assumes failed escalations through the chain; values < 100%\n";
    echo "  represent residual failure after the last tier.\n";
    echo "- Numbers are point estimates from N=3 per cell. See docs/limitations.md.\n";
    echo "\n";

    return 0;
}

exit(main($argv));
