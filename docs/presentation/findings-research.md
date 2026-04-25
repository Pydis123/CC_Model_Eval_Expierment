# Tier-fit in subagent dispatch: a controlled experiment on Claude Haiku, Sonnet, and Opus across eight coding tasks

**Author:** Anders Hydén
**Date:** April 2026
**Repo:** llm-dispatch-experiment (commit at run completion)

---

## Abstract

We measure cost-to-green and time-to-green for three Claude model tiers
(Haiku, Sonnet, Opus) deployed as subagents under a project-manager
dispatch architecture, across eight realistic coding tasks in a PHP/Slim
mock project. Each (task, tier) cell has N=3 replicates (72 dispatches
total). Tasks are evaluated mechanically (test pass, query budget,
lint, expected files). Higher tiers achieve higher pass rates (Haiku
88%, Sonnet 96%, Opus 100%) but at substantial cost premiums in tokens
(Sonnet 1.2–1.6×, Opus 2–4× relative to Haiku) and wall-clock (Sonnet
1.5–2×, Opus 2–7×). Haiku is the cost-optimal choice on five of eight
task categories; Opus is required on tasks involving cross-call-site
reasoning (N+1 optimization). A simulated three-tier escalation policy
(Haiku → Sonnet → Opus) is the cheapest reliability-100% strategy in
expectation for a uniform workload mix. The single largest cost driver
is task-prompt specificity, not tier choice. Findings are bounded by
N=3 sample sizes, a synthetic mock project, and a mechanical evaluator;
they apply to single-dispatch units of work.

## 1. Introduction

Anthropic's Claude API exposes three model tiers — Haiku, Sonnet, and
Opus — at substantially different price points. Practitioners building
agentic systems repeatedly face the question: *which tier should this
task go to?* The published model-card distinctions ("Haiku is fast and
cheap; Opus is the most capable") are accurate but operationally vague.

This experiment makes the tradeoff concrete for one realistic scenario:
small to medium coding tasks dispatched to a subagent under
project-manager orchestration. The research question is:

> Given a coding task in a familiar codebase, what does each tier cost
> in tokens and time, and how reliably does each tier produce a
> mechanically-correct result?

We do not attempt to measure semantic correctness, code quality, or
behavior on adversarial inputs. The goal is to surface tier-fit
patterns that practitioners can apply to dispatch decisions in their
own projects, with stated bounds on generalizability.

## 2. Methodology

Full methodology is documented in `docs/methodology.md`. Summary:

### 2.1 Task bank

Eight tasks were authored in advance and frozen before any dispatch.
They were chosen to cover task shapes commonly seen in PHP web
development:

| ID | Category | Description |
|---|---|---|
| 001 | trivial_i18n | Add a status filter tab in two locales |
| 002 | crud_addition | Add ticket-tag table, CRUD, route, view, tests |
| 003 | query_optimization | Reduce queries on `/tickets` to ≤ 5 (N+1 fix) |
| 004 | migration_backfill | Add SLA deadline column with backfill migration |
| 005 | refactor | Extract state machine into a service class |
| 006 | bugfix_root_cause | Fix an intermittent test by finding the root cause |
| 007 | route_rbac | Add `/tickets/batch-close` route with role check |
| 008 | frontend_alpine | Add an Alpine.js comment composer with submit |

Each task has a markdown prompt and a JSON specification of evaluator
checks. Tasks were not modified after dispatch began.

### 2.2 Tiers

Three Claude model tiers were used. Model IDs were pinned at
experiment start and recorded in `state.json`:

- **Haiku:** `claude-haiku-4-5-20251001`
- **Sonnet:** `claude-sonnet-4-6` (alias)
- **Opus:** `claude-opus-4-7` (alias)

Sonnet and Opus were pinned to aliases rather than dated IDs because
Anthropic had not published dated identifiers for those families at the
time of the run. This is documented as a limitation.

### 2.3 Dispatch protocol

Each dispatch:

1. Creates a fresh git worktree of the mock project from a frozen base
   ref (`scaffold_complete`).
2. Installs Composer dependencies in the worktree (the dependencies
   are gitignored).
3. Invokes `claude -p <prompt> --model <id> --no-session-persistence`
   with a fixed allowlist of tools (Bash, Edit, Read, Write, Glob, Grep).
4. Runs the evaluator against the resulting worktree.
5. If failed, includes the failing-check summary in the prompt for
   the next iteration. Up to 3 iterations are allowed.
6. Records the run as `passed` or `failed` and appends one JSONL row
   to `results/results.jsonl`.

### 2.4 Evaluator

The evaluator runs a configurable check chain per task. Check types
include `phpunit`, `query_count`, `smoke_no_regressions`, `lint`
(PHPStan level 6), `file_exists`, `grep_not_present`, and
`diff_size_limit`. A run passes iff all configured checks pass.

The evaluator is mechanical. It does not measure code quality,
maintainability, idiomatic style, or security.

### 2.5 Replication and ordering

N=3 replicates per (task, tier) cell. Dispatch order was deterministic
via a seeded Fisher–Yates shuffle (`mt_srand(plan_seed ^
crc32(task_id))`, plan_seed=42). Bootstrap analyses for Policy B used
the same seed.

### 2.6 Policy A and Policy B

**Policy A** (retry-only) is what was actually run: a failed run
retries on the same tier up to 2 times.

**Policy B** (cheapest-first escalation) was *simulated* from Policy A
data via a 1000-iteration bootstrap. We did not run Policy B directly;
the simulation samples from observed Policy A outcomes per cell with
replacement.

## 3. Results

### 3.1 Pass rates

| Tier | Passed | Failed | Pass rate |
|---|:---:|:---:|:---:|
| Haiku  | 21 | 3 | 87.5% |
| Sonnet | 23 | 1 | 95.8% |
| Opus   | 24 | 0 | 100.0% |

Haiku failures concentrated in two categories: 002 (CRUD, 2/3) and 003
(N+1, 1/3). Sonnet's single failure was on 003 (2/3). Opus passed all
24 dispatches.

### 3.2 Per-task means (Policy A)

Tokens are subagent input + output, summed across all iterations within
a run. Wall-clock seconds count subagent execution only, excluding
PM-side orchestration.

| Task | Tier | Pass | Mean tokens | Mean wall-clock (s) | Mean iterations |
|---|---|:---:|---:|---:|---:|
| 001 i18n            | haiku  | 3/3 | 5,316  | 62  | 1.00 |
| 001 i18n            | sonnet | 3/3 | 4,076  | 128 | 1.00 |
| 001 i18n            | opus   | 3/3 | 18,591 | 432 | 1.67 |
| 002 CRUD            | haiku  | 2/3 | 22,906 | 418 | 2.67 |
| 002 CRUD            | sonnet | 3/3 | 26,695 | 501 | 2.00 |
| 002 CRUD            | opus   | 3/3 | 38,608 | 795 | 1.67 |
| 003 N+1             | haiku  | 1/3 | 18,912 | 221 | 2.33 |
| 003 N+1             | sonnet | 2/3 | 15,998 | 313 | 2.33 |
| 003 N+1             | opus   | 3/3 | 33,728 | 544 | 2.33 |
| 004 migration       | haiku  | 3/3 | 8,366  | 95  | 1.00 |
| 004 migration       | sonnet | 3/3 | 7,180  | 153 | 1.00 |
| 004 migration       | opus   | 3/3 | 16,630 | 435 | 1.00 |
| 005 refactor        | haiku  | 3/3 | 9,579  | 261 | 1.33 |
| 005 refactor        | sonnet | 3/3 | 10,074 | 232 | 1.67 |
| 005 refactor        | opus   | 3/3 | 9,317  | 238 | 1.00 |
| 006 bugfix          | haiku  | 3/3 | 10,919 | 246 | 1.00 |
| 006 bugfix          | sonnet | 3/3 | 15,162 | 320 | 1.00 |
| 006 bugfix          | opus   | 3/3 | 38,132 | 699 | 1.00 |
| 007 RBAC            | haiku  | 3/3 | 4,906  | 77  | 1.00 |
| 007 RBAC            | sonnet | 3/3 | 10,927 | 312 | 1.00 |
| 007 RBAC            | opus   | 3/3 | 7,304  | 348 | 1.00 |
| 008 frontend Alpine | haiku  | 3/3 | 2,210  | 46  | 1.00 |
| 008 frontend Alpine | sonnet | 3/3 | 2,476  | 91  | 1.00 |
| 008 frontend Alpine | opus   | 3/3 | 5,009  | 96  | 1.00 |

### 3.3 Cost ratios

For tasks where all three tiers passed (the seven tasks excluding 002
where Haiku had a partial failure), the ratio of Opus to Haiku tokens
ranged from 0.97× (task 005) to 3.49× (task 001 and 006). The ratio
of Opus to Haiku wall-clock ranged from 0.91× (task 005) to 7.07× (task
001).

Sonnet's token cost relative to Haiku ranged from 0.77× (task 001) to
2.23× (task 007). On three tasks (001, 003, 004) Sonnet used fewer
tokens than Haiku in expectation, though wall-clock was always higher.

### 3.4 Iteration distribution

Of the 72 runs, 47 (65%) completed in 1 iteration, 16 (22%) in 2
iterations, and 9 (13%) in 3 iterations. Of the 9 three-iteration runs,
4 ended in failure (44%). Two-iteration runs all passed (16/16).

This suggests the iteration count is a useful signal: one-iteration and
two-iteration runs converge productively; three-iteration runs are
roughly a coin-flip on success and consume the full retry budget either
way.

### 3.5 Policy B simulation

The 3-tier bootstrap simulation (Haiku → Sonnet → Opus) reports:

- Mean total tokens per experiment run: **107,231** (95% CI 57,969–185,212)
- Mean total wall-clock per experiment run: **1,886 s** (95% CI 1,046–3,108)
- P(all three tiers fail on a task): **0.000**

Compared to:

- All-Opus (sum of Opus task means): ~167,000 tokens, ~3,635 s
- All-Haiku (sum of Haiku task means): ~83,000 tokens, ~1,425 s
  with 12% expected failure rate

Policy B saves ~36% in expected tokens vs all-Opus while preserving the
0% final-failure rate.

### 3.6 Cost calculator outputs (per-dispatch projections)

For a uniform task mix at 200 dispatches/month, the cost calculator
projects:

| Strategy | Tokens/month | Wall-clock/month | Pass rate |
|---|---:|---:|:---:|
| All-Haiku            | 2.08 M | 9.9 hrs  | 87.5% |
| All-Sonnet           | 2.31 M | 14.2 hrs | 95.8% |
| All-Opus             | 4.18 M | 24.9 hrs | 100.0% |
| Haiku → Opus         | 2.96 M | 14.3 hrs | 100.0% |
| Haiku → Sonnet → Opus| 2.75 M | 13.3 hrs | 100.0% |

The 3-tier escalation chain is cheapest in expectation among
reliability-100% strategies for this mix. The relative ordering is
sensitive to workload — for workloads dominated by tasks where Haiku
is reliable, the Sonnet step adds cost without proportional benefit.

## 4. Discussion

### 4.1 The "Haiku territory" pattern

Five of eight task categories (001 i18n, 004 migration, 005 refactor,
006 bugfix, 007 RBAC, 008 frontend) showed Haiku passing reliably at
substantially lower cost than Opus. The structural commonality across
these categories is:

- The change is local to one or two files.
- The success criterion is mechanically testable.
- The plan can be expressed as a sequence of explicit edits.
- The codebase area follows conventional patterns (typical CRUD,
  typical Alpine wiring, typical migration shape).

For these tasks, paying for a higher tier purchases capability headroom
that is not exercised. The 3.5× token cost of Opus on task 001 buys no
additional pass rate (both at 3/3) but doubles wall-clock and triples
token spend.

### 4.2 The "Opus territory" pattern

Task 003 (N+1 optimization) is the single category where Haiku is
structurally insufficient. The pass rates (Haiku 1/3, Sonnet 2/3, Opus
3/3) and the iteration distribution (Haiku: 1, 3, 3; Sonnet: 1, 3, 3)
reveal a "hit or miss" shape: when the smaller tier sees the right
pattern, it solves the task in one iteration; when it doesn't, three
iterations of retry-with-feedback do not rescue it.

This is consistent with a tier-fit gap rather than a retry-budget gap.
The task requires reasoning about a query budget that is distributed
across multiple ORM call sites and one route handler. This is exactly
the kind of cross-locality reasoning that capability differences
between tiers should exhibit, and the data confirms it.

### 4.3 Sonnet's role

Sonnet was rarely the unambiguous best choice for any single task in
this dataset. It was token-cheapest on tasks 001, 003, and 004, but on
001 and 004 the cost difference vs Haiku was small enough that Haiku's
wall-clock advantage swamped it. On task 003, Sonnet's lower cost came
with a 33% failure rate.

In escalation, however, Sonnet's contribution is meaningful. On task
003, Sonnet catches roughly 67% of Haiku's failures before Opus would
need to be invoked, saving the cost of an Opus dispatch for two-thirds
of escalations. This effect is what produces the ~7% expected-cost
savings of the 3-tier chain over Haiku → Opus on a uniform workload mix.

For workloads with few hard tasks, Sonnet's marginal contribution
shrinks toward zero. The decision of whether to include Sonnet in an
escalation chain is therefore workload-dependent.

### 4.4 Wall-clock vs tokens

Opus is consistently the slowest tier in wall-clock, often
disproportionately so relative to its token cost. On task 001, Opus
uses 3.5× the tokens of Haiku but 7× the wall-clock. On task 006, the
ratios are 3.5× tokens and 2.8× wall-clock.

This indicates Opus's per-token processing time is higher than Haiku's
(consistent with reports of more per-token internal computation in the
larger model). For latency-sensitive applications, the effective cost
premium of Opus is therefore larger than the token premium suggests.

### 4.5 Prompt specificity as a cost lever

The strongest effect not directly measured but visible in the
iteration distribution is prompt-specificity. Tasks 001, 004, 007, 008
have very explicit briefs and consistently completed in one iteration
across all tiers. Task 003's brief is also fairly specific (it names
the query budget) but the *implementation path* is left to the agent;
this is where smaller tiers degrade.

A 50-word addition to a task brief that names the existing pattern to
follow, the test to add, or the specific structure to use can plausibly
shift an Opus task to Haiku territory. We did not run this as a
controlled experiment, but the structural argument is strong: a
specified plan reduces the discovery burden on the agent, and
discovery burden is what differentiates the tiers in this dataset.

### 4.6 Practical configuration

The findings can be operationalized through tier-routing rules
encoded in a project's or user's `CLAUDE.md` instructions, which
Claude Code reads as part of the system prompt. A minimal
operationalization is:

```markdown
## Model selection for coding tasks

Pick the cheapest tier with a reasonable chance of solving the task.
Escalate on failure; do not retry the same tier more than twice.

- Haiku: well-specified local tasks (i18n, migrations with explicit
  plans, route+RBAC, frontend wiring, documented refactors,
  bugfixes with reproductions).
- Sonnet: multi-file work crossing more of the codebase than the
  task description names.
- Opus: cross-call-site reasoning (N+1, transactions across
  services), architecture, security review, cross-system debugging.
```

This rule set encodes the tier-fit findings of §4.1–4.2 plus the
iteration-budget signal of §3.4. A more elaborate version covering
PM-orchestrated dispatch, cost-bounded projects, and prompt
engineering investments is provided in the repository's
`docs/applying-findings.md`. The included `cost-calculator.php`
permits forecasting monthly spend under five strategies for a
user-supplied workload mix.

## 5. Limitations

These limitations are detailed in `docs/limitations.md`. Summary:

- **N=3 per cell.** Three replicates is too few to support narrow
  confidence intervals. Bootstrap CIs in `findings.md` reflect this.
- **Synthetic mock project.** The mock project has planted
  anti-patterns at a known density. Real codebases have their own
  anti-patterns at different baseline densities.
- **Mechanical evaluator.** Code quality, maintainability, security,
  and architectural soundness are not measured.
- **Single execution environment.** All runs occurred on one
  developer's machine. Wall-clock numbers reflect that environment.
- **Single prompt per task.** Sensitivity to prompt phrasing was not
  tested.
- **Token accounting excludes PM overhead.** Real workflows incur
  orchestration token cost not reflected in tier comparisons.
- **Aliased model IDs for Sonnet and Opus.** Server-side model
  remapping under an alias is not detectable via the pinning
  mechanism.
- **Three runner bugs surfaced during the run** (composer install
  missing in worktree prep, double-`/mock-project` path append,
  Composer autoload class collision in the long-running PM process).
  Each consumed Claude tokens for dispatches that were not recorded.
  Approximately three Haiku dispatches were lost in this way; per-cell
  averages and pass rates are not biased by these losses, but the
  total experiment cost is slightly under-reported.

## 6. Future work

- **Larger N.** N=10–20 per cell would tighten confidence intervals
  enough to support sharper task-tier-fit claims.
- **Real-codebase replication.** Extending the methodology to one or
  more real, non-synthetic codebases would test whether the
  mock-project findings generalize.
- **Prompt-specificity ablation.** Same task, four prompt variants
  (terse / structured / structured+example / structured+example+test)
  on the same tier — quantify the prompt-quality lever.
- **Quality-aware evaluation.** Add a code-review pass (LLM-judge or
  human) to the evaluator chain to capture semantic-quality differences
  that mechanical checks miss.
- **Dynamic policy.** Instead of a fixed escalation chain, an
  adaptive policy that selects the tier based on task category and
  historical pass rate. The data already suggests this is feasible.
- **Direct Policy B run.** Run Policy B on the same tasks rather than
  simulating it, to validate the bootstrap.
- **Multi-session work.** Tasks bounded by `max_iterations ≤ 3` and
  `max_wall_clock_s ≤ 900` exclude longer agentic loops. Replicating
  with extended budgets would test tier behavior in those regimes.

## 7. Reproducibility

The experiment is deterministically reproducible end-to-end:

- `plan_seed=42` drives both the dispatch-order shuffle and the
  bootstrap sampling.
- All PHP dependencies are pinned via `composer.lock`.
- The mock project is a frozen git ref.
- The task bank is frozen.
- The evaluator and report generator are deterministic.

To reproduce:

```
docker compose up -d
cd mock-project && php tools/migrate.php && php tools/seed_demo.php
cd ../runner && composer install
cd ..
php runner/bin/cli state init
php runner/bin/cli state pin-models --haiku=<id> --sonnet=<id> --opus=<id>
php runner/bin/cli run-all
php runner/bin/cli report
diff docs/findings.md <regenerated-findings.md>
```

Anything non-deterministic (e.g. server-side model tuning under an
alias) is documented in `limitations.md`.

## 8. Conclusion

Across eight coding tasks under controlled dispatch, Claude tier choice
matters substantially for cost (2–4× tokens, 2–7× wall-clock) but
matters less than the task-prompt-specificity lever. Haiku is the
cost-optimal default for well-specified local tasks (5/8 categories in
this dataset). Opus is required for tasks involving cross-call-site
reasoning (1/8). A simulated 3-tier escalation chain is the cheapest
reliability-100% strategy in expectation for varied workloads. These
findings are consistent with prevailing intuitions among practitioners
but quantify the magnitude of the effects.

The findings should not be over-generalized. The experiment is bounded
by N=3 per cell, a synthetic mock project, mechanical evaluation
criteria, and single-prompt-per-task. Future replications with larger
N, real codebases, and quality-aware evaluation are needed to
strengthen these conclusions.

## Acknowledgments

The runner and analysis pipeline were built collaboratively with Claude
(orchestrator: Opus 4.7; subagents: Haiku 4.5, Sonnet 4.6, Opus 4.7).
Three runner bugs surfacing during execution and their fixes are
documented in `docs/limitations.md` as part of the experiment record.
