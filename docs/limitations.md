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
