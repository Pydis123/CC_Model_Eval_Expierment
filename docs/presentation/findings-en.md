# Which Claude model should you use for what? — An experiment

> **Updated Oct 2026 (v4).** The experiment has been re-run on the current
> lineup (Haiku 5.5 / Sonnet 5.5 / Opus 5.5 / Fable 5.1). The conclusions have
> moved — see [What changed in v4](#what-changed-in-v4-oct-2026). The detailed
> tables in the body show the earlier round (Phase 2, Claude-5), which v4 now
> beats on pass rate.

## TL;DR (current, v4)

- **Two task banks, four model tiers**, N=5 replicates each.
- **Well-specified implementation is a solved problem — full stop.** Every
  tier went **5/5 on every task**. Tier choice there is a cost decision, not a
  quality one.
- **The review bank separated the tiers when first run — not anymore.** On the
  Claude-5 lineup, Haiku failed the reasoning-heavy tasks (2/5). On the 5.5
  lineup **all four tiers pass every discriminating cell (80/80).** Haiku 5.5
  closes exactly the gaps Haiku 4.5 had, at the lowest cost of any tier.
- **Cost is the only real differentiator now.** Haiku 5.5 is the floor by
  13–45×; Sonnet 5.5 and Opus 5.5 run 60–85% cheaper than their predecessors.
- **The safeguard behaviour flipped.** In v3, Opus was the one tier with no
  safeguard interference; in v4 **the whole 5.5 generation hard-blocks
  offensive framings** (its own API error), Opus included. Legitimate
  defensive audits still run.
- **Fable still has no use case:** most expensive, no quality gain, and it
  reroutes/blocks security work. Hard rule stays: never route security work to
  Fable.
- **Optimal strategy:** escalate Haiku → Sonnet → Opus on failure. ~35%
  cheaper than defaulting to Opus for everything.
- **Biggest lever:** how specific your prompt is beats which tier you pick.

---

I run a controlled experiment dispatching coding and review tasks to
Claude's model tiers to answer one practical question: **when is it
worth paying for the more expensive model?** This round adds a second
task bank — review and reasoning work — on top of the implementation
bank from the last round, plus a fourth tier (Fable). The result is a
much sharper picture than before.

## What changed in v4 (Oct 2026)

The experiment has run five times, from an early three-tier pilot to a full
refresh on the current lineup. How the picture moved:

| Round | Date | Lineup | Scope | Headline |
|---|---|---|---|---|
| **v1** | 2026-04 | Haiku 4.5 / Sonnet 5 / Opus 4.8 (3 tiers) | both banks, N=3 | Early pilot; validated the harness. Archived. |
| **v2.1** | 2026-07-06 | + Fable 5 (4 tiers) | implementation, N=5 | **Ceiling** — all 5/5. Tier = cost. |
| **Phase 2** | 2026-07-08 | same 4 tiers | review, N=5 | **Tiers separate.** Haiku fails plan/query-budget (2/5); Opus only flawless tier; Fable reroutes security 100%. |
| **v3** | 2026-07-24 | Opus `4.8 → 5` | 4 cells + probe | Opus 5 a clean drop-in (20/20). Probe: Opus 5 **0/20** rerouted, Fable 5 16/20, Sonnet 5 refused 4/20. |
| **v4** | 2026-10-27/28 | **whole lineup → 5.5** | 4 cells + probe | **New ceiling (80/80).** Haiku 5.5 passes the cells 4.5 failed, cheapest. Probe **flipped**: whole 5.5 gen hard-blocks offensive framings — Opus included. |

**The core of v4:** the tasks that once separated the models no longer do — the
models caught up to the test. What's left to choose on is cost (Haiku 5.5 wins
big) and safeguard behaviour (the whole generation now blocks offensive
framings but lets defensive audits through). Details in
`docs/findings-v4-part1.md` and `docs/findings-v4-part2.md`.

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

## Results — where it got interesting (Phase 2, Claude-5 lineup)

Bank 1 was a ceiling: nothing to show but "100% × 4 tiers." Bank 2 is
the payoff — this is the round where the tiers first separated (Haiku 4.5 /
Sonnet 5 / Opus 4.8 / Fable 5).

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

## The same bank on the 5.5 lineup (v4)

Re-run on the current lineup across the four cells that discriminated above.
Pass · mean recall (where scored) · mean list-price cost per run:

| Task | Haiku 5.5 | Sonnet 5.5 | Opus 5.5 | Fable 5.1 |
|---|---|---|---|---|
| Plan review | 5/5 · 0.95 · $0.07 | 5/5 · 0.98 · $0.36 | 5/5 · 1.00 · $0.67 | 5/5 · 1.00 · $2.46 |
| Security audit | 5/5 · 0.85 · $0.05 | 5/5 · 0.77 · $0.34 | 5/5 · 0.88 · $0.63 | 5/5 · 0.78 · $2.24\* |
| PR code review | 5/5 · 0.88 · $0.02 | 5/5 · 0.80 · $0.20 | 5/5 · 0.88 · $0.35 | 5/5 · 0.90 · $1.41 |
| Query-budget | 5/5 · $0.04 | 5/5 · $0.47 | 5/5 · $0.71 | 5/5 · $3.45 |

\* Fable 5.1 silently rerouted 1 of 5 security-audit dispatches to
`claude-opus-4-8` (still returned a usable, passing result).

Every cell is now 5/5. Haiku 5.5 passes plan review and query-budget — the two
Haiku 4.5 failed — and solved query-budget on the first iteration every time.
Recall is close (Opus 5.5 top on the security audit at 0.88, Haiku 5.5 second
at 0.85); precision ≈1.0. **The safeguard probe inverted v3:** all three
probed models complete the defensive framing but hard-block the offensive ones
(exploit-explain, PoC, offensive tool) with their own API safeguard error —
where Opus 5 in v3 completed all of them. Full write-up:
`docs/findings-v4-part2.md`.

## The two-bank story

1. **Implementation is solved.** If the task is a sequence of explicit
   edits against a named pattern — i18n, RBAC routes, migrations with
   a backfill plan, frontend components, bugfixes with a working
   repro — every tier gets it right, every time. Picking Opus for this
   is paying 2–4× more tokens for a correctness guarantee you already
   have with Haiku.
2. **Review and reasoning is where the tiers actually differ**, and
   the split isn't "small model bad, big model good" — it's sharper
   than that. Haiku 4.5 was genuinely excellent at *mechanical* review
   (spotting seeded defects, reviewing a diff) and genuinely bad at
   *reasoning-heavy* review (auditing a plan's own logic, tracing a
   budget across the codebase). **On the 5.5 lineup that gap closed** —
   Haiku 5.5 passes both — but the distinction is still the right way to
   think about *why* a cheaper model might struggle on a given review.
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

- **Haiku** (`claude-haiku-5-5`) — default for well-specified
  implementation work (i18n, migrations with a backfill plan, RBAC
  routes, frontend components, bugfixes with a working repro). On 5.5 it
  also passes the reasoning-heavy review Haiku 4.5 failed (plan/spec
  review, query-budget), at the lowest cost — a reasonable first try for
  review too. Still name the pattern explicitly; compare tiers in $, not
  tokens (new tokenizer emits ~30% more tokens).
- **Sonnet** (`claude-sonnet-5-5`) — the workhorse for review, and the
  implementation escalation tier. Its exclusive territory shrank once
  Haiku 5.5 took over plan review and query-budget.
- **Opus** (`claude-opus-5-5`) — top escalation rung and blind-safe
  default for high-stakes correctness. Note: Opus 5.5 is NOT
  safeguard-free on security anymore — it hard-blocks offensive framings
  like the rest of the generation; defensive audits still run.
- **Fable** (`claude-fable-5-1`) — no established use case; most
  expensive, no quality gain. Never route security-audit,
  security-review, or adversarial code review to it — silent reroute
  plus overt safeguard blocks.
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

- Raw data: `results/*.jsonl`
- Public summary: `README.md`
- Current findings (v4): `docs/findings-v4-part1.md`, `docs/findings-v4-part2.md`
- Implementation-bank report: `docs/archive/findings-v2.1.md`
- Cost calculator for your own workload:
  `php runner/bin/cost-calculator.php --help`

The entire experiment is deterministically reproducible.

---

*This is one experiment with limited scope. Use it as an updated
mental model, not as ground truth.*
