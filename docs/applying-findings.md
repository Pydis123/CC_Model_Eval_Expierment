# Applying these findings in real projects

This document translates the experiment's results into concrete things
you can do in a real project tomorrow. It is opinionated. The findings
come from two task banks, each run N=5 per tier against Haiku/Sonnet/
Opus/Fable (pinned model IDs): an **implementation bank** (8 tasks —
i18n, CRUD, N+1 fix, migration+backfill, refactor, bugfix-with-repro,
RBAC route, frontend component) and a **review/reasoning bank** (8
tasks — plan review, seeded-security-defect finding, PR code review,
query-budget/N+1 reasoning, multi-tenancy and webhook-delivery
architecture memos, bug-with-no-repro, transactional refactor), scored
by mechanical evaluators, findings-scored precision/recall against a
committed answer key, or an Opus-judged rubric depending on the task.
See `README.md` for the public summary and
[`docs/archive/findings-v2.1.md`](archive/findings-v2.1.md) for the
full implementation-bank report. Use these as defaults, override when
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
afford even one review-task miss — Haiku fails plan/spec review and
query-budget/N+1 reasoning outright, and Sonnet dips on PR code review.
Anywhere "wrong" is expensive (hardware control, auth, payments)
justifies paying the Opus premium on every dispatch rather than
tiering. Costs roughly double a tiered strategy in tokens; eliminates
failure-shape risk on the categories this experiment measured, and is
the reasoned (not measured) safe choice for the categories it didn't
(architecture decisions, cross-system debugging, security review as
judgment).

### B. **All-Haiku** — when the workload is well-specified implementation

Use when: the work is bounded, well-specified, and looks like the
implementation bank — explicit specs, a plan expressible as a sequence
of edits, mechanically testable outcomes. On that category Haiku tied
every other tier at 5/5 for ~40% fewer tokens than Sonnet or Opus. Do
**not** default to all-Haiku for review or reasoning-heavy work — it
fails plan/spec review and query-budget/N+1 analysis outright, not
partially.

### C. **Tiered escalation (Policy B)** — the default for active development

For a varied workload, the 3-tier chain **Haiku → Sonnet → Opus** is
cheapest in expectation. Sonnet earns its slot as the review/analysis
workhorse and as the escalation tier when Haiku fails on implementation
work — it is not a "sometimes helps" middle tier, it is the tier that
actually closes the gap on plan review and query-budget reasoning that
Haiku cannot.

For workloads dominated by tasks where Haiku is reliable (well-specified
implementation, seeded-defect finding, PR code review), **Haiku → Opus**
with Sonnet skipped is within ~5–10% of the 3-tier cost and is a simpler
mental model. Use the cost calculator with your project's actual mix to
decide which is right for you.

Either chain saves roughly a third over all-Opus (~35% in this
experiment's data) and accepts a small latency penalty on the failure
path. This is what most teams should default to.

A useful framing: think of Opus as a *liability budget* you reach for
on escalation or when you have no task-specific evidence, not a
correctness requirement — no task in either bank was ever the sole
thing Opus could pass. Across a project of 100 dispatches, this is
mostly Haiku calls, some Sonnet calls on review/reasoning work, and a
handful of Opus escalations — not a hundred Opus calls.

## 2. CLAUDE.md snippets you can paste

The following blocks are copy-pasteable. Adjust task categories to your
project.

### 2.1 For a generalist coding project

```markdown
## Model selection

Pick the cheapest tier that has a reasonable chance of solving the task.
Escalate on failure — never "just to be safe." Max 2 attempts at the
same tier before escalating; a third same-tier retry is wasted spend.

- **Haiku** — default for ALL well-specified implementation work:
  i18n, migrations with explicit plans, bugfixes with reproductions,
  frontend wiring, RBAC route additions, documented refactors. On
  review/analysis it also nails seeded-security-defect finding and PR
  code review — but FAILS reasoning-heavy review: plan/spec review and
  query-budget/N+1 analysis. Route those two straight to Sonnet, don't
  spend a Haiku attempt first. Haiku follows prompts literally but does
  NOT read the codebase proactively — name the patterns explicitly in
  the prompt.
- **Sonnet** — the workhorse for review/analysis, and the escalation
  tier for implementation. Handles plan/spec review and query-budget/N+1
  reasoning where Haiku fails outright; dips on PR code review — escalate
  to Opus on doubt there. On implementation work, dispatch Sonnet on
  Haiku failure or when the prompt can't name the pattern precisely
  enough for Haiku — never "to be safe."
- **Opus** — top escalation rung and blind-safe default for high-stakes
  correctness. No task in this experiment required Opus over Sonnet —
  Sonnet matched or nearly matched it on most review tasks at lower
  cost. Use it when you have no task-specific evidence and the cost of
  being wrong is high (architecture decisions, cross-system debugging,
  security review as judgment — none of these are measured by this
  experiment), not as a default tier for measured task types.
- **Fable** — no dispatch case in this experiment. HARD RULE: never
  route security-audit, security-review, or adversarial code review to
  Fable — its dual-use safeguards can silently reroute those dispatches
  (up to 100% reroute observed on a seeded-security-defect task,
  producing zero usable output). Sonnet matches Fable on cost and
  quality everywhere it was tested, with no reroute risk.

If a Haiku attempt fails the evaluator on a well-specified implementation
task and the failure is a small typo or misnaming, retry once at the same
tier. If it's structural, escalate to Sonnet. For plan review or
query-budget/N+1 reasoning tasks, don't retry Haiku at all — the failure
there is a reasoning gap, not a typo, and a retry won't fix it.
```

### 2.2 For a PM-orchestrated project (subagents under a planner)

Same baseline as above, plus:

```markdown
## PM dispatch policy

- **Default tier** is Haiku for well-specified implementation work
  unless the task description explicitly requires cross-system
  reasoning (named in the brief, not assumed).
- **Plan/spec review and query-budget/N+1 analysis default to
  Sonnet**, not Haiku — this is a known Haiku failure mode, not a
  "try cheap first" case.
- **Iteration budget per dispatch**: 2 attempts at the chosen tier,
  then escalate or stop. Three same-tier retries is wasted budget.
- **Plan the work before picking the tier.** A precise plan can move a
  task from "needs Sonnet" to "Haiku territory." Spend planning effort
  before tier-cost.
- **Pin the tier per dispatch**, not per-conversation. Mixing tiers
  inside a single agent confuses cost accounting.
- **Never route security-audit or adversarial code review to Fable** —
  see the hard rule above.
```

### 2.3 For a project with strict cost ceilings

```markdown
## Cost-bounded dispatch

This project has a monthly token budget of $TOKENS_PER_MONTH. Therefore:

- All well-specified implementation dispatches start at Haiku — it
  ties every other tier there for ~40% fewer tokens.
- Plan/spec review and query-budget/N+1 analysis go straight to
  Sonnet — Haiku fails these outright, so starting there just burns
  an iteration before you escalate anyway.
- Sonnet handles the rest of review/analysis: seeded-defect finding,
  PR code review (escalate to Opus on doubt), architecture-decision
  memos.
- Opus is reserved for cases with no task-specific evidence and a high
  cost of being wrong — architecture review, security review as
  judgment, complex debugging spanning multiple subsystems. Each Opus
  dispatch must be justified in the dispatch log.
- Never route security-audit or adversarial code review to Fable, even
  under cost pressure — its safeguards can reroute the dispatch and
  return zero usable output instead of a cheaper answer.
- Tasks failing 2 same-tier iterations are escalated to a human review
  queue, not auto-escalated to Opus.
```

## 3. How to recognize a "Haiku task" vs a "Sonnet task" vs an "Opus task"

These are field-derived heuristics from the two task banks; treat them
as a starting checklist and refine on your own data.

**Haiku is right when several of these are true:**

- The change is local to one or two files / one logical unit.
- The plan can be expressed as a numbered list of edits.
- "What good looks like" is testable mechanically (passes lint, passes
  one new test, generates expected file, query count under a budget
  *that's already named in the prompt*).
- The task was given as a spec, not a discovery problem.
- The codebase area is conventional (typical CRUD, typical Slim/Laravel
  shape, typical Alpine wiring).
- It's a review task with a bounded, mechanical answer — seeded-defect
  finding against a known pattern, or a PR diff review.

**Escalate to Sonnet when:**

- The task is reviewing or critiquing a *plan or spec* rather than
  executing one — Haiku fails this category, not just dips on it.
- The success criterion requires the agent to figure out *whether* and
  *where* a resource/query budget is exceeded, not just execute a named
  fix — query-budget/N+1 reasoning is a Sonnet-and-up task, not a
  Haiku-with-retries task.
- It's a review/analysis task you don't yet have local evidence Haiku
  clears (PR code review dipped to 3/5 for Sonnet in this experiment
  too — treat both tiers as "escalate on doubt" for this category).

**Escalate to Opus when:**

- You have no task-specific evidence any lower tier handles it —
  architecture decisions beyond a rubric-scored memo, cross-system
  debugging, multi-service transaction reasoning at scale,
  security review as human judgment, PM/orchestration work. These are
  genuinely unmeasured by this experiment; Opus is the reasoned
  default there, not a measured requirement.
- The cost of a wrong answer is high enough that Opus's reliability is
  worth paying for even without a measured edge over Sonnet.
- Sonnet has already failed twice on the same task.

If the dispatch fails: read the failed-checks output. On a
well-specified implementation task, if it's a small typo or misnaming,
retry once at the same tier; if it's structural (a test was misread, a
service was misnamed, a query was wrong), escalate. On a review or
reasoning task, don't retry the same tier at all — a failure there
tends to be a reasoning gap the model will repeat, not a slip it will
correct on a second try.

## 4. Prompt engineering as a tier-multiplier

This experiment surfaced two different shapes of the "same" problem.
On the implementation bank, the N+1 fix task (task 003 — eager-load a
relation to cut query count) hit 5/5 on every tier: once the fix is a
named pattern to execute, it's solved at any tier. On the review bank,
the query-budget task (task 108 — decide whether an endpoint's query
count is bounded, and why) is where Haiku fails 2/5: it can execute a
named fix but does not reliably reason about *whether* one is needed
without being told what to look for. Sonnet clears both at 5/5.

The lesson: prompt precision is what turns a Sonnet-or-Opus reasoning
task into a Haiku execution task. The prompt's job is to hand the model
the discovery step you already did, so it only has to execute.

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

A ~50-word prompt addition that does these things is a bigger cost
lever than tier choice — it can move work from "needs Sonnet" (or
Opus) to "Haiku is fine," which is cheaper than paying the tier
premium per dispatch. Invest in the prompt before you invest in the
tier.

## 5. When to NOT apply these findings

- **Greenfield prototyping.** When the goal is exploring a design space,
  Opus from the start is justified — its capability headroom matters
  more than its cost.
- **Tasks the experiment does not cover.** Architecture decisions
  beyond the two rubric-scored memos, cross-system debugging,
  multi-service transaction reasoning at scale, security review as
  human judgment (distinct from seeded-defect *finding*, which Haiku
  handles fine), PM/orchestration work, multi-session epics,
  cross-repo work, anything agentic in the autonomous-loop sense. The
  experiment is bounded to single-dispatch units of work with a frozen
  prompt each — no adversarial-prompt robustness was tested.
- **High-stakes commits.** Auth, payments, data migrations against
  production schemas, anything irreversible. These overlap heavily
  with the unmeasured categories above — Opus's edge there is
  reasoned, not measured. Use Opus and add human review anyway; a
  passing evaluator on a lower tier does not mean the result is safe
  in ways the evaluator can't see.
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
lower-tier runs that maxed at 2 iterations and still failed — wasted
token and time spend that would have been avoided by either a better
prompt or a higher tier from the start. The decision point is *before*
the dispatch.
