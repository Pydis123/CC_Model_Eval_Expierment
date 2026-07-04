# Limitations

This experiment measures real behavior under a narrow set of conditions. Before
drawing conclusions from `findings.md`, read this list.

## Small sample size

N=3 replicates per (task, tier) cell. Three samples is not enough to produce
narrow confidence intervals, and the bootstrap CIs in `findings.md` reflect
that. Per-task numbers are noisy; trends across all 8 tasks are more reliable
than any single-cell figure.

## Retry-only policy bias

Policy A (retry within the same tier, up to 3 iterations) is what we actually
ran. This policy structurally advantages the higher tiers: a failed haiku run
burns three attempts worth of haiku tokens, whereas Policy B (cheapest-first
escalation) would have stopped at one failed haiku attempt and moved on.

Policy B simulation addresses this but uses the same underlying data — the
simulation is not an independent run.

## Semantic correctness not judged

The evaluator runs mechanical checks (tests pass, query count under limit,
files exist, lint passes). It does not measure code quality, maintainability,
idiomatic style, API design, security, or architectural soundness. A task can
pass the evaluator while containing code that a human reviewer would reject.

## Mock project is synthetic

The mock project includes planted anti-patterns ("natural mediocrity"): a known
N+1 query, an inline state machine that should be a service, a deliberately
intermittent test. Real codebases have their own anti-patterns but at different
baseline densities. Task difficulty here is not representative of real-world
task difficulty.

## Task bank bias

The 8 tasks are biased toward tractable, single-session work: i18n tweaks,
CRUD additions, targeted refactors, query-count fixes, a bug fix. There are no
multi-week epics, no large-scale refactors across service boundaries, no tasks
requiring coordination across multiple services or teams. Findings about
"model X at tier Y" generalize only to tasks of similar shape.

## Token accounting

PM-side overhead (orchestration, evaluator output reads, state management) is
recorded separately under `tokens_pm_overhead` but excluded from tier
comparisons. In a real workflow where PM overhead scales with dispatch count,
tiers that require more retries or more PM intervention would look worse than
they do here.

## Environment drift

Rate limits, server-side model tuning, and Claude Code runtime updates can
shift during the run window. Pinned model IDs (`pinned_models` in
`experiment_config.json`) guard against silent model swaps but not against
server-side changes that retain the same model ID.

## Bootstrap assumptions

The bootstrap CI in `findings.md` assumes the 3 observed runs per cell are
representative of the underlying distribution. With N=3, this assumption is
fragile. Bootstrap CIs are narrower than parametric CIs would be under the
same N — take them as a lower bound on uncertainty, not an upper bound.

## Single execution environment

All runs happen on one developer's machine, one network, one timezone, one
Docker Desktop version. Wall-clock numbers reflect that environment and will
shift on different hardware or network paths.

## No adversarial robustness check

The task definitions are fixed. We do not test robustness against small
prompt perturbations, distracting instructions, or prompt injection. A
behavior-change from a one-word prompt edit would not be caught.

## Runner bugs during the 2026-04-24 run

Three runner bugs surfaced during the first production `run-all` invocation
and consumed Claude tokens for dispatches that never reached
`results/results.jsonl`:

1. `WorktreeManager::prepare()` did not `composer install` the worktree's
   `mock-project/` (vendor/ gitignored) → `PhpunitCheck` spawned
   `./vendor/bin/phpunit` with ENOENT. Two Haiku dispatches on
   `001-i18n-status-flik` wasted before the fix landed.
2. `RunNextCommand` passed `$worktreePath . '/mock-project'` as the
   coordinator's outer worktree path; evaluator Checks then appended
   `/mock-project` a second time. Same ENOENT symptom, different root
   cause.
3. `QueryCountProbe::count()` used `require_once` on
   `mock-project/vendor/autoload.php` in-process inside the long-running
   `run-all` PHP, colliding `ComposerAutoloaderInit<hash>` on the second
   task-003 worktree. One dispatch wasted on the crashing triplet before
   `QueryCountCheck` was switched to a subprocess call.

Total wasted: ~3 dispatches (mix of Haiku and possibly higher tiers),
none recorded. The 72-run denominator is preserved; only the wasted
dispatches' tokens are un-attributed. Not expected to bias findings —
the wasted runs repeat on the same triplets as the recorded ones.

## User-global CLAUDE.md leakage

Each subagent is dispatched from `/tmp/llm-disp-run-<id>/mock-project/` after
the experiment-root `CLAUDE.md` has been removed from the worktree. However,
Claude Code's default system-prompt auto-discovery still walks up from the
CWD to the filesystem root, and the user-global `~/.claude/CLAUDE.md` — if
present — is read by the subagent on every dispatch.

This file is not experiment-specific in the normal case (it typically
describes general PM/subagent working methods), but it can bias the
subagent's self-understanding. Reviewers running the experiment on a
different machine will see different subagent behavior to the extent that
their user-global CLAUDE.md differs. We considered using `--bare` to
suppress this, but it requires `ANTHROPIC_API_KEY` and is incompatible with
the Claude Code OAuth session that the experiment relies on.

## Ground-truth leakage in v1 (working-tree prune added in v2)

In v1, each worktree checked out the whole experiment repo and deleted only
the root `CLAUDE.md`. The subagent's cwd was `mock-project/`, but the frozen
task specs under `../tasks/*.json` (including success criteria) were readable
in the working tree. For v1's implementation tasks the criteria ≈ the prompt,
so the effect is minor. v2 prunes the worktree to `mock-project/` only,
removing those files from the **working tree**.

Note this prune is necessary but **not sufficient** for Phase 2. Because the
runner uses `git worktree add`, the worktree's `.git` is a linked worktree
sharing the main repo's object database, so committed content stays
recoverable via plumbing (`git show <ref>:<path>`, `git cat-file`) even after
the working-tree prune. Phase 2, which ships ground-truth defect sets that
must not be readable by the audited subagent, additionally requires that
ground truth be unreachable from the worktree's object database (kept outside
the repo, exported via a non-linked checkout, or with `.git` access stripped).
The Phase 2 design spec records this as a hard prerequisite.

## v2 config change (documented per repo rule)

`experiment_config.json` was changed for v2 (`n_replicates` 3→5, `tiers` gains
`fable`, `experiment_name` → `llm-dispatch-v2-phase1`). This is a new
experiment version, not a mid-experiment mutation: v1 is complete and its data
is archived at `results/results-v1-2026-04.jsonl`.

## Safeguard routing (new non-reproducibility source)

Fable 5 carries dual-use safety measures that can, on some prompts, reroute a
dispatch to a different model or refuse in-band. The runner records
`dispatch_disposition` per run and halts on a suspected silent swap. Interference
is content-, account-, and time-dependent: two reviewers on different accounts
may observe different rates. This is analogous to server-side model retuning —
it cannot be fully reproduced.

## Network-outage re-queue on 2026-07-04 (state.json edited mid-experiment)

During the overnight v2 run, a local internet outage starting ~22:45Z on
2026-07-03 caused 5 consecutive dispatches to fail with
`error_category=claude_cli_is_error` / `dispatch_disposition=error`
(002-crud-ticket-tag: opus n=4, sonnet n=2, sonnet n=3, fable n=3, haiku
n=1), after which run-all aborted by design (5-error circuit breaker,
crash dump `results/runner-crash-20260703T225715.json`).

These are infrastructure failures, not model behavior; leaving them as
`failed` would bias those cells. Per the append-only rule the error rows
stay in `results/results.jsonl`; the 5 runs were re-queued by moving them
from `completed_runs` back to the front of `remaining_runs` in
`state.json` (same run_ids, `claimed_at` reset; backup kept at
`state.json.bak-20260704`). Re-running a recorded run_id appends a second
row for it, so the aggregator keeps only the **last** row per run_id —
earlier rows for a re-queued run are superseded observations, preserved
in the log for audit but excluded from analysis.
