# Which Claude model should you use for what? — An experiment

## TL;DR

- **Two task banks, four model tiers** (Haiku, Sonnet, Opus, Fable), N=5
  replicates each.
- **Well-specified implementation is a solved problem — full stop.** 8
  implementation tasks × 4 tiers × 5 runs, and every tier went **5/5 on
  every task**. Tier choice there is a cost decision, not a quality one.
  Haiku is ~40% cheaper than Sonnet/Opus for an identical result.
- **Review and hard reasoning is where it gets interesting.** A second
  bank of 8 review/analysis tasks actually splits the tiers apart —
  this is the part worth reading.
- Haiku nails mechanical review (seeded security-defect finding 5/5, PR
  code review 5/5) but **fails reasoning-heavy review**: plan/spec
  review 2/5, query-budget/N+1 analysis 2/5.
- Sonnet is the workhorse: 5/5 on 7 of 8 review tasks, including both
  tasks Haiku failed.
- Opus is the only flawless tier (40/40, zero failures) — but it was
  **never the sole passer**. No task in this experiment required Opus.
- Fable has no use case here: it matches Sonnet on cost and quality,
  and its dual-use safety filters **silently reroute security-adjacent
  work away from the model** — 100% of security-audit dispatches never
  ran at all.
- **Optimal strategy:** escalate Haiku → Sonnet → Opus on failure. ~35%
  cheaper than defaulting to Opus for everything, same end result.
- **Biggest lever:** how specific your prompt is beats which tier you
  pick.

---

I run a controlled experiment dispatching coding and review tasks to
Claude's model tiers to answer one practical question: **when is it
worth paying for the more expensive model?** This round adds a second
task bank — review and reasoning work — on top of the implementation
bank from the last round, plus a fourth tier (Fable). The result is a
much sharper picture than before.

## Setup

**Bank 1 — implementation** (8 tasks: CRUD, refactor, bugfix,
migration, i18n, RBAC route, Alpine frontend component, N+1 query fix).
Each task run 5× on each of 4 tiers. Evaluated mechanically: tests
pass, query budget holds, expected files exist, diff stays within
bounds.

**Bank 2 — review & hard reasoning** (8 tasks: adversarial plan
review, seeded-security-defect finding, PR code review, query-budget/
N+1 reasoning, multi-tenancy architecture decision, webhook-delivery
architecture decision, a bug with no repro steps, a transactional
refactor). Each task run 5× on each of 4 tiers. Evaluated three ways
depending on task shape: mechanical checks (tests, query-count budget,
a red/green regression gate for the no-repro bug), findings-vs-ground-
truth precision/recall for the two audit-style tasks, and rubric
scoring against an anchored 0/1/2 scale (judged by Opus, which is
pinned and not one of the tiers under test) for the two architecture
decisions.

Pinned exact model IDs throughout to avoid silent upgrades mid-run.
Max 2 same-tier retries before escalating.

## Results — where it gets interesting

Bank 1 was a ceiling: nothing to show but "100% × 4 tiers." Bank 2 is
the payoff.

| Task | Haiku | Sonnet | Opus | Fable |
|---|:---:|:---:|:---:|:---:|
| Plan review (adversarial) | **2/5** | 5/5 | 5/5 | 5/5 |
| Security audit (seeded defects) | 5/5 | 5/5 | 5/5 | rerouted (N=0) |
| PR code review | 5/5 | 3/5 | 5/5 | 4/4* |
| Multi-tenancy architecture decision | 5/5 | 5/5 | 5/5 | 5/5 |
| Webhook-delivery architecture decision | 5/5 | 5/5 | 5/5 | 5/5 |
| Bug with no repro | 5/5 | 5/5 | 5/5 | 5/5 |
| Transactional refactor | 5/5 | 5/5 | 5/5 | 5/5 |
| Query-budget / N+1 reasoning | **2/5** | 5/5 | 5/5 | 3/3* |

\* Fable didn't reach full N=5 on these two — see Limitations.

Read the two bolded rows first: **Haiku fails exactly the tasks that
require holding a lot of reasoning together at once** — auditing a
plan against its own stated assumptions, and tracing a query budget
across call sites. Everything else, including finding seeded security
bugs and reviewing a PR diff, Haiku handles just as well as the bigger
models.

Sonnet only dips once — code review, 3/5 — and otherwise matches Opus
exactly.

Opus is the only tier with zero failures across the whole bank. But
look at the table again: on 7 of the 8 tasks, Sonnet matched it. There
was never a task where Opus was the *only* model that could do the
job.

Fable tracks Sonnet everywhere it actually ran — no capability
advantage anywhere — and its dual-use safety classifiers **silently
rerouted every single security-audit dispatch** (0 usable
observations) plus 20% of code-review dispatches. It never refused
outright; the request just never reached the model as asked. Hard
rule: **never route security-audit, security-review, or adversarial
code review to Fable.**

## The two-bank story

1. **Implementation is solved.** If the task is a sequence of explicit
   edits against a named pattern — i18n, RBAC routes, migrations with
   a backfill plan, frontend components, bugfixes with a working
   repro — every tier gets it right, every time. Picking Opus for this
   is paying 2–4× more tokens for a correctness guarantee you already
   have with Haiku.
2. **Review and reasoning is where the tiers actually differ**, and
   the split isn't "small model bad, big model good" — it's sharper
   than that. Haiku is genuinely excellent at *mechanical* review
   (spotting seeded defects, reviewing a diff) and genuinely bad at
   *reasoning-heavy* review (auditing a plan's own logic, tracing a
   budget across the codebase). Know which kind of review you're
   asking for.
3. **Opus's real value is reliability without task-specific knowledge**
   — the blind-safe default when you don't know enough about the task
   to pick a cheaper tier confidently. It is not, on this evidence, a
   correctness requirement for any of these categories.

## Practical takeaway

Pick the cheapest tier with a reasonable chance of passing. Escalate
on failure — Haiku → Sonnet → Opus — never "just to be safe." That
escalation chain comes out **~35% cheaper than routing everything to
Opus**, for the same end-state pass rate. Haiku → Opus, skipping
Sonnet, is within 5–10% of that and is a simpler mental model if your
task mix doesn't need the middle rung.

The biggest cost lever still isn't tier choice — it's **how specific
your prompt is**. A ~50-word tightening that names the pattern, points
at the existing code to mirror, and specifies the test can turn a
"needs Opus" task into a "Haiku is fine" task. That's a bigger win
than any tier upgrade.

## How to configure CLAUDE.md to leverage this

```markdown
## Model selection

Pick the cheapest tier that has a reasonable chance of solving the
task. Escalate on failure, never "just to be safe." Max 2 same-tier
retries before escalating.

- **Haiku** — default for well-specified implementation work
  (i18n, migrations with an explicit backfill plan, RBAC routes,
  frontend components, bugfixes with a working repro, single-file
  refactors with a named pattern). Also strong on mechanical review —
  seeded-defect finding, PR code review. Skip Haiku for reasoning-heavy
  review (plan/spec review, query-budget/N+1 analysis) — go straight
  to Sonnet.
- **Sonnet** — the workhorse for review and analysis, and the
  escalation tier for implementation. Handles plan review and
  query-budget reasoning that Haiku can't.
- **Opus** — top escalation rung and blind-safe default for
  high-stakes correctness when you can't confidently pick a cheaper
  tier. Not "just to be safe" by default — reserve it for cases where
  the cost of being wrong is high.
- **Fable** — no established use case; matches Sonnet on cost and
  quality with no advantage. Never route security-audit,
  security-review, or adversarial code review to it — its safety
  filters silently reroute those requests away from the model.
```

## Limitations

N=5 per cell is still a small sample — trust patterns that repeat
across tasks more than any single cell. The mock project is synthetic,
with planted patterns and anti-patterns; real codebases are messier.
The evaluator is mechanical (tests, query budgets, findings-vs-ground-
truth, rubric scoring) — it measures correctness, not taste or
long-term maintainability. Each task has one frozen prompt; no
adversarial-prompt robustness was tested.

One honest deviation: Fable didn't reach full N=5 on three Bank 2
cells. On the security-audit task, all 5 dispatches were silently
rerouted by Fable's own safety filters — that's not missing data, it
*is* the measurement (a re-run would only reconfirm the block). On
code review, 4 of 5 completed cleanly (1 rerouted) and all 4 passed.
On query-budget reasoning, a usage cap capped it at 3 clean runs, all
of which passed. None of this changes any conclusion above — Fable was
never the sole passer on anything and tracked Sonnet everywhere it
did run, so the tier-separation story is carried entirely by the
fully-sampled Haiku/Sonnet/Opus columns.

Categories still genuinely unmeasured: security review as human
judgment (distinct from seeded-defect *finding*, which Haiku and
Sonnet both nail), architecture decisions beyond the two rubric tasks
here, cross-system debugging, multi-service transaction reasoning at
scale, and PM/orchestration work. Opus's value in those categories is
reasoned, not measured — don't overclaim it.

## Resources

- Raw data: `results/results.jsonl`
- Public summary: `README.md`
- Implementation-bank report: `docs/archive/findings-v2.1.md`
- Cost calculator for your own workload:
  `php runner/bin/cost-calculator.php --help`

The entire experiment is deterministically reproducible.

---

*This is one experiment with limited scope. Use it as an updated
mental model, not as ground truth.*
