# Applying these findings in real projects

This document translates the experiment's results into concrete things
you can do in a real project tomorrow. It is opinionated. The opinions
are bounded by the same caveats as `conclusions.md`: N=3, synthetic
mock-project, mechanical evaluator. Use these as defaults, override when
your context disagrees.

The companion document [`tier-picker.md`](tier-picker.md) is the one-page
cheat sheet — pin it where you need it. This file is the reasoning behind
the cheat sheet.

## 1. Pick a tier strategy at the project level

Before any single dispatch decision, pick the project's tier strategy.
Three options:

### A. **All-Opus** — when correctness dominates cost

Use when: the project is small, the work is unfamiliar, you are early in
the codebase and don't yet know which tasks are local, or you cannot
afford even a 12% raw failure rate (hardware control, auth, payments,
anything where "wrong" is expensive). Costs ~2× a tiered strategy in
tokens; eliminates failure-shape risk.

### B. **All-Haiku** — when speed and cost dominate

Use when: the work is bounded, you have explicit specs, you can absorb
~12% rework rate, and the rework cost is acceptable. Examples:
batch-translation work, repetitive boilerplate, tightly-specified
migrations. Wall-clock is 3–7× faster than all-Opus on tasks Haiku
clears.

### C. **Tiered escalation (Policy B)** — the default for active development

For a varied workload, the 3-tier chain **Haiku → Sonnet → Opus** is
cheapest in expectation. Sonnet earns its slot by catching a useful
fraction of Haiku's failures on harder task categories before Opus has
to be paid for.

For workloads dominated by tasks where Haiku is reliable (trivial,
migration, RBAC, frontend, bugfix-with-repro), **Haiku → Opus** with
Sonnet skipped is within ~5–10% of the 3-tier cost and is a simpler
mental model. Use the cost calculator with your project's actual mix to
decide which is right for you.

Either chain saves ~30–35% over all-Opus and accepts a small latency
penalty on the failure path. This is what most teams should default to.

A useful framing: think of Opus as a *liability budget*. You don't pay
for it on every dispatch; you pay for it when Haiku has already failed.
Across a project of 100 dispatches, this is dozens of Haiku calls and a
handful of Opus calls — not a hundred Opus calls.

## 2. CLAUDE.md snippets you can paste

The following blocks are copy-pasteable. Adjust task categories to your
project.

### 2.1 For a generalist coding project

```markdown
## Model selection

Pick the cheapest tier that has a reasonable chance of solving the task.
Escalate on failure; do not retry the same tier more than twice.

- **Haiku** — boilerplate, i18n, migrations with explicit plans, simple
  bugfixes with reproductions, frontend wiring, route+RBAC additions,
  documented refactors.
- **Sonnet** — multi-file CRUD additions, work that requires reading
  more of the codebase than the task description names, anything where
  Haiku has previously failed twice on a similar task.
- **Opus** — query-budget reasoning (N+1, transactions across services),
  architecture decisions, debugging across multiple subsystems,
  security review, anything where the model has to *discover* the right
  structure rather than execute a named structure.

If a Haiku attempt fails the evaluator and the failure is not a simple
typo, escalate to Opus directly. Sonnet rarely closes the gap that
Haiku missed.
```

### 2.2 For a PM-orchestrated project (subagents under a planner)

Same baseline as above, plus:

```markdown
## PM dispatch policy

- **Default tier** is Haiku unless the task description explicitly
  requires cross-system reasoning (named in the brief, not assumed).
- **Iteration budget per dispatch**: 2 attempts at the chosen tier,
  then escalate or stop. Three same-tier retries is wasted budget.
- **Plan the work before picking the tier.** A precise plan can move a
  task from "needs Opus" to "Haiku territory." Spend planning effort
  before tier-cost.
- **Pin the tier per dispatch**, not per-conversation. Mixing tiers
  inside a single agent confuses cost accounting.
```

### 2.3 For a project with strict cost ceilings

```markdown
## Cost-bounded dispatch

This project has a monthly token budget of $TOKENS_PER_MONTH. Therefore:

- All dispatches start at Haiku.
- Sonnet is disabled for ordinary task dispatch.
- Opus is reserved for: architecture review, security audit, complex
  debugging spanning >2 subsystems. Each Opus dispatch must be
  justified in the dispatch log.
- Tasks failing 2 Haiku iterations are escalated to a human review
  queue, not auto-escalated to Opus.
```

## 3. How to recognize a "Haiku task" vs an "Opus task"

These are field-derived heuristics; treat them as a starting checklist
and refine on your own data.

**Haiku is right when several of these are true:**

- The change is local to one or two files / one logical unit.
- The plan can be expressed as a numbered list of edits.
- "What good looks like" is testable mechanically (passes lint, passes
  one new test, generates expected file).
- The task was given as a spec, not a discovery problem.
- The codebase area is conventional (typical CRUD, typical Slim/Laravel
  shape, typical Alpine wiring).

**Escalate to Opus when several of these are true:**

- The work crosses subsystem boundaries (DB schema → ORM → service →
  route → view).
- The success criterion includes a reasoning step ("queries should be
  bounded" — agent must figure out *which* queries).
- The task is "find the bug" without a reliable reproduction.
- The codebase has unusual patterns the agent must adapt to.
- The change is architectural (new service boundary, new abstraction).

If the dispatch fails: read the failed-checks output. If it's a small
typo or misnaming, retry once at the same tier. If it's structural — a
test was misread, a service was misnamed, a query was wrong — escalate.

## 4. Prompt engineering as a tier-multiplier

This experiment showed that Haiku's failures on task 003 (N+1) had a
"hit or miss" shape: when the tier saw the right pattern, one iteration
was enough; when it didn't, three iterations were not enough. The
prompt's job is to push every dispatch into the "saw the right pattern"
regime.

**Investments that move tasks down a tier (Opus → Sonnet, Sonnet → Haiku):**

- **Name the structure.** Instead of "fix the slow ticket page," write
  "the route `/tickets` issues N+1 queries on the comments relation;
  use eager loading so the total queries for the page are ≤ 5."
- **Cite the existing pattern.** "Follow the pattern in
  `TicketCommentService::loadAll()` — do the same for comments on
  tickets."
- **Specify the test.** "Add a test that asserts query count ≤ 5; the
  existing test in `TicketsTest::testIndexQueryCount()` is a template."
- **Pre-resolve ambiguity.** If two reasonable approaches exist, pick
  one in the prompt. Don't make the model choose.

A 50-word prompt addition that does these things can reliably turn a
1/3-pass-rate Haiku run into a 3/3-pass-rate Haiku run. That is a >$1
saving per dispatch at experimental token rates, and a 5–10× wall-clock
improvement.

## 5. When to NOT apply these findings

- **Greenfield prototyping.** When the goal is exploring a design space,
  Opus from the start is justified — its capability headroom matters
  more than its cost.
- **Tasks the experiment does not cover.** Multi-session epics,
  cross-team coordination, work spanning multiple repos, anything
  agentic in the autonomous-loop sense. The experiment is bounded to
  single-dispatch units of work.
- **High-stakes commits.** Auth, payments, data migrations against
  production schemas, anything irreversible. Lower tiers might pass the
  evaluator and still be wrong in ways the evaluator cannot see. Use
  Opus and add human review.
- **First N dispatches in a new codebase.** Until you have evidence
  that Haiku handles your codebase's style, default conservatively.
  Re-evaluate after 20–30 dispatches.

## 6. Calibrating to your own task distribution

Your project's task mix is not the same as the experiment's. Use
[`runner/bin/cost-calculator.php`](../runner/bin/cost-calculator.php) to
forecast monthly cost given your distribution:

```
php runner/bin/cost-calculator.php \
  --policy=B \
  --dispatches-per-month=200 \
  --mix=trivial:0.3,crud:0.2,migration:0.15,refactor:0.1,bugfix:0.15,frontend:0.1
```

It will print expected tokens per month under each of the three
strategies (all-Opus, all-Haiku, tiered escalation), with bootstrap
confidence intervals. Treat the output as order-of-magnitude guidance,
not a quote.

## 7. The single highest-leverage habit

If you take only one practice from this:

> **Plan before tier-pick. Tier-pick before dispatch. Don't dispatch
> first and re-tier later.**

The experiment's most expensive runs were not Opus runs. They were
Haiku runs that maxed at 3 iterations and still failed — wasted token
and time spend that would have been avoided by either a better prompt
or a higher tier from the start. The decision point is *before* the
dispatch.
