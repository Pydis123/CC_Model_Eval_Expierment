# Conclusions — Model–task fit

This document is an analytical layer on top of the raw results. The
numbers live in `README.md` (public summary) and
`docs/archive/findings-v2.1.md` (implementation-bank report); per-cell
Phase 2 numbers are hand-aggregated from `results/results.jsonl`. What
follows is *what the numbers mean* for choosing a model tier.

All claims here are bound by the constraints in `limitations.md` — most
importantly `N=5` per cell and the synthetic mock-project. Treat the
recommendations as **strong defaults**, not hard rules.

## The central reframe: two banks, two different stories

Earlier versions of this document drew conclusions from a single
implementation bank at N=3. There are now **two independently-run
banks at N=5**, and nearly every tier-fit claim below depends on which
one a task belongs to: **Bank 1 (implementation, tasks 001–008) is a
ceiling** — every tier passed every task — while **Bank 2 (review &
hard reasoning, tasks 101–108) is where tiers actually separate**. Read
the per-task tables with that split in mind rather than looking for one
universal ranking.

## TL;DR

1. **Well-specified implementation is solved across all tiers.** Bank 1
   is a ceiling — pick the cheapest tier that can execute the plan.
   Haiku's whole-bank mean token spend is ~40% below Sonnet/Opus for an
   identical outcome (Haiku ~97k vs Sonnet ~156k vs Opus ~163k vs Fable
   ~162k, summed across all 8 tasks).
2. **Review and hard reasoning is where tiers actually separate** —
   that is the entire point of Bank 2, and it's where the interesting
   decisions live now.
3. **Haiku** is the default for implementation and also nails
   *mechanical* review (seeded-security-defect finding 5/5, PR code
   review 5/5) — but it fails *reasoning-heavy* review: plan/spec
   review 2/5 and query-budget/N+1 analysis 2/5. For those two
   categories, skip straight to Sonnet. Haiku follows prompts literally
   but does not read the codebase proactively — name the patterns
   explicitly in the prompt.
4. **Sonnet is the review/analysis workhorse.** 5/5 on 7 of 8 Bank 2
   tasks, including both categories Haiku failed. It dips only on code
   review (3/5 — escalate on doubt). On implementation it never beats
   Haiku's pass rate (ceiling) and costs ~60% more tokens there, but is
   the correct default escalation tier out of Haiku on both banks.
5. **Opus passed every Bank 2 cell (40/40, zero failures)** but was
   **never the sole passer** — Sonnet matched it on 7 of 8 tasks at
   roughly 15% fewer tokens. No measured task in either bank required
   Opus. Its demonstrated value is reliability *without* task-specific
   knowledge: the blind-safe default and top escalation rung, not a
   correctness requirement. The old "query-budget/N+1 → Opus only" rule
   is retired — Sonnet went 5/5 on that task too.
6. **Fable has no dispatch case.** It matches Sonnet on both cost and
   quality everywhere it ran cleanly, with no measured advantage. Its
   dual-use safeguards also **silently reroute security-adjacent
   dispatches**: 100% rerouted on the security-audit task (zero usable
   observations), 20% on code review. **Hard rule: never route
   security-audit, security-review, or adversarial code review to
   Fable.** Sonnet dominates it on every axis that matters for dispatch
   (equal cost, equal quality, no reroute risk).

## Per-task-type recommendation

"Primary" means: dispatch here first. "Fallback" means: if the
evaluator rejects, escalate here. For Bank 1, every tier passes every
task — "Primary" reflects the cheapest tier for an identical result,
not a correctness bet.

### Bank 1 — implementation (ceiling)

| Category (task) | Structural property | Primary | Fallback |
|---|---|---:|---:|
| i18n / locale (001) | One-line schema, repetitive, plan spells out exactly what to write | **Haiku** | Sonnet |
| CRUD addition — ticket tags (002) | Multiple files, model + repo + route + view + tests | **Haiku** | Sonnet |
| N+1 query fix (003) | ORM call-site fix against an explicit plan | **Haiku** | Sonnet |
| Migration + backfill, SLA deadline (004) | Mechanical SQL with an explicit backfill path | **Haiku** | Sonnet |
| State-service refactor (005) | Whole-class restructure, explicit target shape | **Haiku** | Sonnet |
| Intermittent-test bugfix (006) | Reproduce failure, identify cause, write regression test — plan gives the repro | **Haiku** | Sonnet |
| RBAC route, batch close (007) | Add a route, wire authorization, correct status codes | **Haiku** | Sonnet |
| Alpine.js frontend component (008) | Composer with submit handler + state | **Haiku** | Sonnet |

Every row above passed 5/5 on every tier. There is no per-task tier-fit
signal left in Bank 1 to differentiate primaries — the fallback exists
only as an escalation reflex if a *specific* dispatch fails for
non-tier reasons (bad prompt, flaky environment), never because Haiku
is structurally weak on the task type.

### Bank 2 — review & hard reasoning (tiers separate)

| Category (task) | Haiku | Sonnet | Opus | Fable | Primary | Fallback |
|---|---:|---:|---:|---:|---:|---:|
| Plan review, adversarial (101) | 2/5 | 5/5 | 5/5 | 5/5 | **Sonnet** | Opus |
| Security audit, seeded defects (102) | 5/5 | 5/5 | 5/5 | N=0 (100% rerouted) | **Haiku** | Sonnet — never Fable |
| Code review, PR diff (103) | 5/5 | 3/5 | 5/5 | 4/4 (20% rerouted) | **Haiku** | Opus (skip Sonnet — it dips) |
| Multi-tenancy, architecture decision (104) | 5/5 | 5/5 | 5/5 | 5/5 | **Haiku** | Sonnet |
| Webhook delivery, architecture decision (105) | 5/5 | 5/5 | 5/5 | 5/5 | **Haiku** | Sonnet |
| Bug with no repro (106) | 5/5 | 5/5 | 5/5 | 5/5 | **Haiku** | Sonnet |
| Transactional refactor (107) | 5/5 | 5/5 | 5/5 | 5/5 | **Haiku** | Sonnet |
| Query-budget / N+1 perf reasoning (108) | 2/5 | 5/5 | 5/5 | 3/3 | **Sonnet** | Opus |

The two rows where Haiku is *not* the right primary — 101 (plan
review) and 108 (query-budget reasoning) — are exactly the two
categories flagged in the TL;DR as reasoning-heavy. Everywhere else in
Bank 2, Haiku matches the top tiers on a mechanical-finding or
mechanical-verification task. Fable never appears as a primary or sole
fallback: it tracks Sonnet where it ran cleanly and carries the reroute
risk described above, so it earns no dispatch case even on tasks where
its clean-run numbers look fine.

## Where the data is decisive — and where it isn't

### Decisive findings (pattern is robust within the dataset)

- **Bank 1's ceiling is total.** 40/40 on every tier means tier choice
  in implementation work is a pure cost decision.
- **Bank 2 separates on reasoning depth, not task domain.** Haiku's two
  failures (101, 108) both require synthesizing information beyond
  what's locally visible in a diff or file; its five clean passes
  (102–107) show it handles well-specified *finding* or *verification*
  work just fine — it's open-ended synthesis that breaks it.
- **Opus's perfect record never bought it exclusivity.** 40/40 with
  zero failures, but Sonnet matched it on 7 of 8 Bank 2 tasks at lower
  cost — evidence for Opus as the reliable top rung, not as a tier any
  specific task requires.
- **Fable's safeguard interference is a measured liability, not a
  theoretical one.** 100% reroute on security-audit and 20% on code
  review are hard numbers from actual dispatch attempts.

### Indecisive findings (could go either way)

- **Sonnet's 3/5 on code review (103)** is the one dip in an otherwise
  clean record — real enough to escalate on doubt, but whether it's a
  structural weakness or noise at N=5 isn't resolved here.
- **Fable's true capability on security-audit (102) is unmeasurable by
  construction.** All 5 dispatches were rerouted before producing
  usable output (N=0) — that's not missing data, it *is* the
  measurement. Its narrower cells on 103 (N=4) and 108 (N=3) don't
  change any conclusion — Fable was never the sole passer anywhere it
  ran, and never beats Sonnet on the dispatch decision even where it
  cleared cleanly.
- **Architecture-decision tasks (104, 105) are graded by an Opus judge,
  and Opus is also one of the tiers under test** — both pinned to the
  same model ID. A same-model grading bias on rubric tasks where Opus
  participates is not something this dataset can rule out.

## What the N=5 replicate structure tells us

The current banks score pass/fail across five independent dispatches
per cell rather than tracking iteration counts within a single capped
run (the old Policy A/B framing with `max_iterations ≤ 3` no longer
describes how these two banks are scored). The signal that survives is
simpler: a cell's pass count out of 5 *is* the tier-fit measurement.

- **5/5** is a clean pass — no ambiguity about tier fit for that task.
- **2/5** (Haiku on 101 and 108) is not "occasionally unlucky" — with
  five independent attempts, a task that a tier gets right less than
  half the time is a genuine capability gap, not sampling noise.
- Read against the escalation policy: **max 2 attempts on the same
  tier per task before escalating** — a tier that hasn't passed by the
  second same-tier attempt is close to a coin flip on the third, and
  should escalate rather than retry a third time. Cells like 101 and
  108 are exactly what "structurally a poor fit" looks like at the
  pass-count level: retries within a tier don't rescue it, escalating
  to the next tier does (Sonnet clears both at 5/5).

## The economics of escalation

The escalation chain **Haiku → Sonnet → Opus, escalating only on
failure**, is cheapest in expectation for a varied workload — roughly
**35% under an all-Opus baseline**. Skipping Sonnet (**Haiku → Opus**)
lands within **~5–10%** of the three-tier chain and is a simpler mental
model; which one fits depends on how much of the workload looks like
Bank 2's reasoning-heavy tasks (where Sonnet earns its slot by catching
Haiku's misses) versus Bank 1-style implementation work.

**Bank 1 makes this escalation math trivial.** With every tier passing
every implementation task, the "escalate on failure" branch almost
never fires there — the only lever left is picking the cheapest tier
up front (Haiku).

**The interesting escalation economics live entirely in Bank 2.** Two
of eight tasks (101, 108) need the Haiku → Sonnet step to avoid a
roughly 60% failure rate at the first tier; the other six clear at
Haiku and would pay Sonnet's ~60%+ token premium for nothing. A
workload-aware policy — Haiku default, Sonnet-first only for tasks
resembling plan review or cross-cutting performance reasoning — beats a
uniform three-tier chain applied blindly to every review task.

## What this dataset cannot conclude

- **Security review as human judgment.** The seeded-defect *finding*
  task (102) is measured (Haiku/Sonnet both clear it) — but open-ended
  security review as a judgment call, distinct from spotting planted
  defects against a known list, remains unmeasured.
- **Architecture decisions beyond the two rubric tasks (104, 105).**
  Both hit ceiling, but that's two data points in one direction — it
  says nothing about architecture work with more genuine ambiguity.
- **Cross-system debugging and multi-service transaction reasoning at
  scale.** Task 107 touches this territory but at a scope small enough
  to hit ceiling.
- **PM / orchestration work, multi-session work, cross-repo work.**
  Every task in both banks is a single bounded dispatch.
- **Prompt sensitivity and adversarial robustness.** Each task has one
  frozen prompt. We don't know whether different phrasing would shift
  Haiku's 2/5 on 101 or 108 toward 5/5 or toward 0/5.
- **Code and decision quality beyond the evaluator's rubric.** The
  mechanical, findings-scored, and rubric-scored evaluators (the last
  an Opus judge against an anchored 0/1/2 scale) don't fully stand in
  for a human reviewer's judgment on elegance, architectural
  defensibility, or security reasoning that doesn't reduce to a
  checklist.
- **N=5 is still small.** Trust cross-task patterns over any single
  cell's exact count.

## The one strong qualitative claim

The cleanest cross-task signal in the data, now sharper with two banks
instead of one:

> **Prompt specificity is a bigger lever on outcome than tier choice.**
> Higher tiers don't win by being smarter in the abstract — they win
> on tasks where the work requires synthesizing information the prompt
> didn't hand over explicitly.

Bank 1 is the clean demonstration: when a task's plan is an explicit
sequence of edits, tier stops mattering at all — Haiku matches Opus
100% of the time. Bank 2 shows the same mechanism from the other side:
Haiku's two failures (101, 108) require reasoning beyond what's locally
visible — cross-cutting adversarial review, a query-budget analysis
spanning ORM call sites — while its five clean passes (102–107) are
still locally scoped and checkable against explicit criteria, however
serious the ask.

The practical lever is the one this project has argued from the start:
a prompt that names the pattern, points at the specific files in play,
and specifies what "done" looks like can move a task from "needs
Sonnet or Opus" to "Haiku is fine" — cheaper than paying a tier
premium on every dispatch of that category. Bank 2's two Haiku
failures are the sharpest test of that claim yet, and it remains
untested: the frozen-prompt limitation above is exactly why.
