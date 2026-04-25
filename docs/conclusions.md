# Conclusions — Model–task fit

This document is an analytical layer on top of `findings.md`. The numbers
are there; what follows is *what they mean* for choosing a model tier.

All claims here are bound by the constraints in `limitations.md` — most
importantly `N=3` per cell and the synthetic mock-project. Treat the
recommendations as **strong defaults**, not hard rules.

## TL;DR

1. **Haiku is the right default** for the majority of well-scoped coding
   tasks. It clears 5 of 8 task types at full pass-rate and at 50–70%
   lower token cost than Opus.
2. **Opus is the safety tier**, not the daily driver. It hit 24/24 passes
   but at 2–4× the tokens and 2–7× the wall-clock of Haiku on tasks
   where Haiku already passes. Reserve it for tasks Haiku/Sonnet are
   structurally bad at — typically anything requiring whole-system
   reasoning.
3. **Sonnet is rarely the optimal choice in this dataset.** It is almost
   never the cheapest *and* highest-passing tier on a single task. It
   shows up as a viable middle ground but loses to Haiku where Haiku
   suffices, and to Opus where Haiku breaks.
4. **For tiered escalation, the 3-tier chain (Haiku → Sonnet → Opus) is
   cheapest in expectation for a varied workload.** Sonnet does catch a
   useful share of Haiku's misses on the harder task categories. For
   workloads dominated by tasks where Haiku is reliable (trivial,
   migration, RBAC, frontend, simple bugfix), the Sonnet step adds cost
   without proportional benefit and Haiku → Opus is fine.

## Per-task-type recommendation

Each row below names a task category from the task bank, the structural
property that drives tier choice, the recommended primary tier, and a
fallback if the primary fails. "Primary" means: dispatch here first.
"Fallback" means: if evaluator rejects, escalate here.

| Category (example task) | Structural property | Primary | Fallback |
|---|---|---:|---:|
| Trivial i18n / locale (001) | One-line schema, repetitive, plan spells out exactly what to write | **Haiku** | Sonnet |
| CRUD addition (002) | Multiple files, model + repo + route + view + tests | **Sonnet** | Opus |
| Query optimization / N+1 (003) | Requires reasoning across ORM call sites and one query budget | **Opus** | — |
| Migration + backfill (004) | Mechanical SQL with a backfill path, plan is explicit | **Haiku** | Sonnet |
| Service-extract refactor (005) | Whole-class restructure, state machine to be moved | **Haiku** | Sonnet |
| Bugfix root-cause (006) | Reproduce intermittent failure, identify cause, write regression test | **Haiku** | Opus |
| Route + RBAC (007) | Add a route, wire authorization, return correct status codes | **Haiku** | Sonnet |
| Frontend Alpine.js component (008) | Composer with submit handler + state | **Haiku** | Sonnet |

The single category where Haiku is *not* the right primary in this dataset
is **query optimization (N+1)**, where Haiku passed only 1/3 attempts
(33%). Sonnet did better at 2/3 (67%) but still left a one-in-three risk.
Only Opus hit 3/3.

## Where the data is decisive — and where it isn't

### Decisive findings (pattern is robust within the dataset)

- **Trivial / mechanical tasks favor Haiku heavily.** Tasks 001, 004, 007,
  008 all show Haiku passing on a single iteration with token usage 50–60%
  below Opus. The wall-clock advantage is even larger (3–7×). For tasks
  where the plan can be spelled out in advance, paying for a higher tier
  is paying for capability you don't need.
- **Opus is consistently the slowest tier in wall-clock**, even on tasks
  it nails on iteration 1. Token usage and time are correlated — more
  internal "thinking" per token. If end-to-end latency matters more than
  raw token cost, Opus is *more* expensive than its sticker price implies.
- **N+1 query reasoning breaks the smaller tiers.** Task 003 is the only
  task category where Haiku is structurally unreliable. The pattern in
  iterations is telling: Haiku had one run that passed in 1 iteration and
  two that maxed at 3 iterations and failed. Same pattern for Sonnet.
  This is "hit or miss" — when the tier sees the right pattern, it solves
  it fast; when it doesn't, more retries don't help. That is a tier-fit
  problem, not a retry budget problem.

### Indecisive findings (could go either way)

- **Sonnet's place in the hierarchy.** Sonnet is token-cheapest on tasks
  001, 003, 004 — but on 001 and 004 the difference vs Haiku is small
  enough that Haiku's wall-clock advantage swamps it. On 003, Sonnet's
  win is real but still leaves a 33% failure rate. With more tasks in the
  bank we'd see whether Sonnet has a true sweet spot or is always the
  "second best at everything" tier in this dispatch pattern.
- **Refactor task (005)** is the only task where Opus is *cheapest* in
  tokens. The margin is tiny (3% under Sonnet, 3% under Haiku), and the
  iteration counts suggest Opus picks the right approach immediately
  while Haiku/Sonnet try a less-fit approach first and have to redo. With
  larger refactors than this one, that effect could grow — but we don't
  have data on larger refactors.

## What iteration count tells us

`iterations_used` (1, 2, or 3 in this experiment, capped at 3 by Policy A)
is a useful signal beyond the binary pass/fail.

- **iter=1**: tier got it right first try. This is the cheap regime.
- **iter=2**: tier needed the failed-checks feedback to correct course.
  Almost always cheaper than escalating to a higher tier, *but* it
  doubles wall-clock.
- **iter=3** ending in fail: this is the wasteful regime. Three full
  dispatches' worth of tokens and time, no result. This pattern is
  strongest on Haiku-task-002 (3,2,3) and on the failing Haiku/Sonnet
  runs of task-003 (1,3,3). When the tier is structurally a poor fit,
  retries don't rescue it — they amplify the loss.

**Practical rule:** if a tier hits iteration 2 on a task category, that
category is on the boundary of its competence. Hitting iteration 3 means
the task is past its competence and the next dispatch should escalate,
not retry.

## The economics of Policy A vs Policy B

Numbers from `findings.md`:

- **Policy A, all-Opus baseline:** sum of Opus mean tokens across the 8
  tasks ≈ 167,000 tokens. Pass rate 24/24.
- **Policy A, all-Haiku baseline:** sum of Haiku mean tokens ≈ 83,000
  tokens. Pass rate 21/24 (3 failures across two task categories).
- **Policy B simulated (Haiku → Sonnet → Opus):** mean 107,000 tokens
  per experiment run, 0% probability of all three tiers failing.

Policy B sits between the two Policy A baselines, as expected. Compared
to all-Opus, it saves ~36% in expected tokens at the cost of variance
(95% CI 58k–185k). Compared to all-Haiku, it adds ~30% to expected
tokens but eliminates the 12% raw failure rate.

A **Haiku → Opus** Policy B (skipping Sonnet) is ~7% more expensive in
expectation for a uniform workload mix. Sonnet earns its slot mainly on
the harder task categories (002, 003) where it catches a non-trivial
share of Haiku's misses. For workloads with few hard tasks, Haiku → Opus
is essentially equivalent and gives a cleaner mental model.

## What this dataset cannot conclude

- **Real-codebase tasks vs mock-project tasks.** The mock project is
  small, has planted anti-patterns at a known density, and has a
  predictable test surface. Real codebases vary wildly. Findings here
  generalize cautiously.
- **Multi-session work.** Every task here was bounded by `max_iterations
  ≤ 3` and `max_wall_clock_s ≤ 900`. Anything that would need an hour-long
  session, multiple plan/execute/review loops, or cross-PR coordination
  is outside the data.
- **Code quality.** The evaluator is mechanical — it checks tests pass,
  query counts hold, files exist, lints clean. None of that catches
  ugly-but-passing code, security issues, or architectural mistakes that
  pass the gates. Tier choice may have a quality dimension this
  experiment does not measure.
- **Prompt sensitivity.** Each task has one frozen prompt. We don't know
  whether a slightly different phrasing would shift Haiku's failure rate
  on tasks 002/003 to zero or to two-thirds.
- **Tier behavior under adversarial conditions.** Distractor instructions,
  partial information, ambiguous specs — none of these are tested.
  Findings reflect "task description is honest and complete."

## The one strong qualitative claim

The cleanest cross-task signal in the data is this:

> Higher tier doesn't mean better outcome — it means **more tolerance
> for ambiguity in the task description** and **better reasoning across
> non-local code**.

Tasks where the plan is explicit and the work is local (i18n, migration,
RBAC, frontend, refactor, bugfix-with-repro) are *Haiku territory*.
Tasks where the work crosses ORM/query boundaries or requires the tier
to discover the right structure (N+1, complex CRUD with cross-cutting
concerns) are where higher tiers earn their cost.

When you write the dispatch prompt, the choice of tier is downstream of
how completely the prompt specifies the work. A prompt-engineering
investment can shift a task from "needs Opus" to "Haiku is fine." That
is the practical lever this experiment surfaces.
