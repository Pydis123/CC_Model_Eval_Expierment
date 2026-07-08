# LLM Dispatch Experiment

A controlled experiment measuring the **cost and reliability of Claude model
tiers** when used as subagents for realistic coding and code-review tasks under
project-manager (PM) dispatch orchestration.

The practical question it answers: *when a PM delegates a well-scoped task to a
subagent, which model tier is the cheapest one that still gets it right — and
when do you actually need to pay for a stronger model?*

## Current status

The experiment has been recalibrated for the Claude 5 model landscape (Haiku
4.5 / Sonnet 5 / Opus 4.8 / Fable 5). **Two task banks have now been run to
completion:**

1. **Implementation bank** (`v2.1`, tasks `001–008`) — eight well-specified
   coding tasks. Result: a **ceiling** — every tier passed every task. Tier
   choice affects only cost, not outcome.
2. **Review / hard-reasoning bank** (`Phase 2`, tasks `101–108`) — eight harder
   tasks (adversarial plan review, security audit, PR review, architecture
   decisions, a no-repro bug, a transactional refactor, a query-budget fix).
   Result: this bank **separates the tiers** — the interesting data.

An earlier three-tier run (`v1`, 2026-04, N=3) is complete and archived. A
`v2.0` implementation run was collected but **discarded** for two harness
confounds and re-run as `v2.1` (see [Deviations](#deviations-things-that-did-not-go-to-plan)).

> **On the generated report.** `docs/findings.md` is normally produced by
> `runner/bin/cli report`. For Phase 2 the generator cannot yet run (it hard-requires
> N=5 per cell and miscounts safeguard-rerouted runs as passes — see
> [Limitations](#limitations)), so the Phase 2 numbers below were **hand-aggregated
> deterministically** from `results/results.jsonl` with zero model dispatch.
> The implementation-bank report is committed at
> [`docs/archive/findings-v2.1.md`](docs/archive/findings-v2.1.md).

## TL;DR — the headline findings

**1. Well-specified implementation is a solved problem across all tiers.**
On the implementation bank every tier — Haiku, Sonnet, Opus, Fable — passed
**5/5 on all 8 tasks**. For work that is a sequence of explicit edits against a
named pattern, tier choice is a **cost** decision, not a correctness one. Haiku
used ~40% fewer tokens than Sonnet/Opus for the same result.

**2. Review and hard reasoning is where tiers separate.** On the Phase 2 bank:

- **Haiku fails the reasoning-heavy tasks** — plan/spec review (2/5) and
  query-budget / N+1 analysis (2/5) — but still nails mechanical review:
  security-defect finding and PR code review at 5/5.
- **Sonnet is the workhorse** — 5/5 on 7 of 8 tasks including everything Haiku
  failed; it dipped only on PR code review (3/5).
- **Opus was the only flawless tier (40/40)** — but **never the sole passer**.
  Sonnet matched it on 7 of 8 tasks at ~15% fewer tokens. **No task required
  Opus.** Its measured value is *reliability without task-specific knowledge* —
  a blind-safe default, not a correctness requirement.
- **Fable has no dispatch case** — it matches Sonnet on both cost and quality
  (no advantage anywhere) **and** its dual-use safeguards silently reroute
  security-adjacent work (100% of the security-audit dispatches). Sonnet
  dominates it. **Hard rule: never route security work to Fable.**

**3. Prompt specificity is a bigger cost lever than tier choice.** A 50-word
tightening that names the pattern and points at existing code can move a task
from "needs Opus" to "Haiku is fine" — cheaper than paying the Opus premium per
dispatch.

**4. Cheapest reliable strategy is escalation, not a fixed tier.**
Haiku → Sonnet → Opus (escalate only on failure) is the cheapest
reliability-100% strategy in expectation — roughly **35% under all-Opus**.

## What is tested

Both banks run against the same synthetic target: **`mock-project/`**, a
PHP 8.4 / Slim 4 / Twig / Alpine.js / MariaDB support-ticket system with
deliberately planted anti-patterns ("natural mediocrity" — a known N+1 query,
an inline state machine that should be a service, an intermittently-failing
test, etc.). The domain is deliberately *not* a blog/CMS so models can't
auto-complete it from training data.

### Bank 1 — Implementation (`001–008`, `v2.1`)

Eight canonical task types, each a self-contained, well-specified coding job:

| Task | Type |
|---|---|
| 001 | i18n / translation-row additions |
| 002 | CRUD feature (ticket tags) |
| 003 | N+1 query fix |
| 004 | Schema migration + backfill (SLA deadline) |
| 005 | Single-file refactor (extract state-machine service) |
| 006 | Bugfix with root cause (intermittent test) |
| 007 | RBAC route addition (batch close) |
| 008 | Alpine.js frontend component (comment composer) |

### Bank 2 — Review & hard reasoning (`101–108`, Phase 2)

Eight harder tasks designed to *stress the reasoning tiers*, mixing three
evaluator families (see [How it is tested](#how-it-is-tested)):

| Task | Type | What it demands |
|---|---|---|
| 101 | Adversarial plan / spec review | Find the flaws planted in a written plan |
| 102 | Security audit | Find seeded vulnerabilities in the codebase |
| 103 | PR code review | Find the defects in a proposed diff |
| 104 | Multi-tenancy architecture decision | Reason about a cross-cutting schema/design choice |
| 105 | Webhook-delivery design | Reason about retries, idempotency, ordering |
| 106 | Bug with no reproduction | Diagnose a reopened-ticket SLA bug from symptoms |
| 107 | Transactional refactor | Get multi-step DB consistency right |
| 108 | Query-budget / performance | Cut a route's query count under a hard budget |

## How it is tested

Every run is fully isolated. The runner exports a frozen copy of the mock
project into a fresh throwaway repo, dispatches one subagent at a pinned model
via the `claude` CLI, then scores the result mechanically. There is **no human
in the scoring loop** — the evaluator is deterministic.

Three evaluator families are used:

1. **Mechanical checks** (implementation bank + tasks 106/107/108): run PHPUnit
   subsets and the full smoke suite, assert a route's MariaDB query count is
   under a task budget, assert files exist, assert a regex is absent, assert the
   diff stays under a line cap, and — for the no-repro bug — a **red/green
   regression gate** (the model's new test must *fail* on the buggy baseline and
   *pass* on its fix). A run passes iff all configured checks pass.
2. **Findings-scored** (tasks 101/102/103): the model emits a `findings.json`;
   it is scored by **precision / recall against a committed ground-truth answer
   key** (`tasks/ground-truth/`). A run passes iff recall and precision clear
   the task's thresholds — i.e. it found the planted defects *without*
   hallucinating a pile of false ones.
3. **Rubric-scored** (tasks 104/105): the model emits a decision memo, scored by
   an **Opus judge** against an anchored 0/1/2 rubric with a pass threshold.

**Why the judge is Opus, not the top tier.** The judge triages the most
safeguard-exposed content in the experiment (vulnerability findings). Fable's
dual-use safeguards can reroute or refuse *inside the measurement instrument*,
so the judge is pinned to `claude-opus-4-8`. A constant judge bias survives
relative tier comparison; the anchored rubric bounds it.

**Answer-key isolation.** For the findings-scored tasks the ground-truth key
must be unreachable by the audited subagent. Runs execute in a non-linked
`git archive` export (so the key is not recoverable from the git object
database — a hostile probe confirmed this), and every transcript is scanned by a
`ContaminationDetector` that flags any reference to the key's path; flagged runs
are discarded and re-dispatched. One residual vector (an absolute filesystem
path outside the repo) is *detected rather than sandboxed* — see
[Limitations](#limitations).

## The model tiers

Four tiers, pinned to exact model IDs and verified on every dispatch:

| Tier | Pinned model ID |
|---|---|
| Haiku | `claude-haiku-4-5-20251001` |
| Sonnet | `claude-sonnet-5` |
| Opus | `claude-opus-4-8` |
| Fable | `claude-fable-5` |
| *(judge)* | `claude-opus-4-8` |

`N=5` replicates per `(task, tier)` cell. Nominal size: 8 tasks × 4 tiers × 5 =
**160 runs per bank**.

## Results

### Implementation bank (`001–008`, N=5)

**Every tier passed 5/5 on every task — a total ceiling.** The only difference
is cost. Summed across all 8 tasks, mean tokens for one full pass:

| Tier | Pass rate | ~Tokens (8-task total) |
|---|---|---|
| Haiku | 40/40 | ~97k |
| Sonnet | 40/40 | ~156k |
| Opus | 40/40 | ~163k |
| Fable | 40/40 | ~162k |

Haiku is ~40% cheaper than Sonnet/Opus for identical outcomes. Full per-task
tables: [`docs/archive/findings-v2.1.md`](docs/archive/findings-v2.1.md).

### Review & hard-reasoning bank (`101–108`, N=5)

This is the bank that separates the tiers. **Pass rate (clean runs that passed
/ clean runs):**

| Task | Haiku | Sonnet | Opus | Fable | Fable safeguard interference |
|---|---|---|---|---|---|
| 101 plan-review | **2/5** | 5/5 | 5/5 | 5/5 | 0% |
| 102 security-audit | 5/5 | 5/5 | 5/5 | **N=0** | **100% rerouted** |
| 103 code-review | 5/5 | **3/5** | 5/5 | 4/4 | 20% rerouted |
| 104 multi-tenancy | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 105 webhook-delivery | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 106 bug-no-repro | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 107 transactional-refactor | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 108 query-budget-perf | **2/5** | 5/5 | 5/5 | 3/3 | 0% |

Mean tokens per run: Haiku ~17k · Sonnet ~20k · Opus ~23k · Fable ~20k.

Reading the table:
- **Bold-low Haiku cells** (101, 108) are the reasoning-heavy tasks Haiku can't
  reliably do. Everything else it does at 5/5.
- **Sonnet's 3/5 on 103** is the one place a cheaper tier (Haiku) was steadier —
  escalate-on-doubt, don't assume Sonnet always dominates Haiku.
- **Opus is the only column with no failures**, but every Opus pass is matched
  by a cheaper tier somewhere.
- **Fable's 102 cell is N=0** — not a failure, a *reroute*. See below.

## Deviations (things that did not go to plan)

Documented in full in [`docs/limitations.md`](docs/limitations.md); the
load-bearing ones:

### Fable did not run to full N — and why it doesn't change the conclusions

Fable fell short of a clean N=5 in three Phase 2 cells:

- **102 security-audit → N=0.** All 5 dispatches were **silently rerouted** by
  Fable's dual-use safeguards before producing a usable audit. This is **not
  missing data — it *is* the measurement.** The finding is precisely "Fable
  refuses/reroutes security work," and a full re-run would only re-confirm the
  block. No conclusion depends on obtaining clean Fable audit observations here;
  the conclusion is that you cannot use Fable for this at all.
- **103 code-review → N=4** (1 of 5 rerouted). The 4 clean runs all passed.
- **108 query-budget → N=3** (Fable's weekly usage cap was hit before the last
  2 ran). The 3 clean runs all passed.

**Why running Fable to full N would not move any headline:**

- Fable was **never the sole passer** on any task and matched Sonnet on both
  cost and quality across the whole bank. Its verdict — *"no advantage over
  Sonnet, plus a reroute liability"* — rests on cells that *are* fully sampled.
- On 103 and 108, Fable already tracks the ceiling (4/4 and 3/3 clean passes) in
  cells where the tier-separation story is carried entirely by the fully-sampled
  Haiku/Sonnet/Opus columns (e.g. 108's "Haiku 2/5, Sonnet & Opus 5/5"). Two or
  three more Fable samples would tighten a confidence interval, not flip a
  verdict.
- The only thing left genuinely unmeasured is *Fable's clean pass-rate on a
  security audit* — and that is unmeasurable by construction, because the
  safeguard blocks it. That gap is itself the actionable result.

So the incompleteness affects the **width of two Fable confidence intervals**,
not the direction of any finding. Every dispatch rule derived from this bank
holds whether or not those cells reach N=5.

### The v2.0 implementation dataset was discarded

The first four-tier implementation run (`v2.0`, 160 runs) was thrown out after
review found two harness confounds: (1) a **diff-trap** where the worktree prune
left infrastructure files as unstaged deletions that the diff-size check
counted, forcing every run to waste an iteration repairing them; and (2)
**operator-context leakage** — dispatched sessions read the operator's personal
`CLAUDE.md`, including a table telling each model what its own tier is "expected"
to handle. It was re-run clean as `v2.1` with a sanitized environment and
`--setting-sources project`. The v2.0 data is retained (renamed) as a
measurement of the operator-harness effect, not used for conclusions.

### Other documented deviations

- **Network outages** during the overnight runs produced a handful of
  infrastructure-error rows; these were re-queued (same deterministic run IDs)
  and the error rows are superseded at aggregation. They are infrastructure, not
  model behavior.
- **Task 106** initially failed for all tiers on a broken red/green check
  (a symlinked `vendor/` let PHP resolve the fix during the "should-fail" phase).
  The check was fixed and 106 re-run clean (15/15 correct) — the earlier 0/6 was
  100% a check bug.

## Limitations

Read [`docs/limitations.md`](docs/limitations.md) before drawing conclusions.
The short list:

- **N=5 is still small.** Per-cell pass rates are noisy; trust the cross-task
  pattern over any single cell.
- **The evaluator is mechanical.** It scores tests-pass / query-count /
  precision-recall / rubric — **not** code quality, maintainability, idiomatic
  style, or security-as-judgment. A run can pass with code a human would reject.
- **The mock project is synthetic**, with planted anti-patterns at a chosen
  density. Task difficulty here is not real-world task difficulty.
- **Safeguard routing is non-reproducible.** Fable's interference rate is
  content-, account-, and time-dependent; two reviewers may measure different
  rates for the same tasks.
- **Answer-key isolation has one residual vector** — a maximally adversarial
  agent could read the ground-truth key by absolute filesystem path. This is
  *detected and discarded*, not sandboxed, because a blind audit model has
  neither motive nor path knowledge to try. Accepted for a private calibration.
- **The Phase 2 `report` generator cannot regenerate `docs/findings.md` yet** —
  it hard-requires N=5 and would miscount rerouted runs as passes. The numbers
  in this README are hand-aggregated; a generator fix is an open item.
- **No adversarial-prompt robustness, no multi-session or cross-repo work, one
  frozen prompt per task, one execution environment.** Findings generalize only
  to tasks of similar shape.

## Documentation map

| Path | Contents | Currency |
|---|---|---|
| [`docs/archive/findings-v2.1.md`](docs/archive/findings-v2.1.md) | Implementation-bank report (per-task tokens, CIs, Policy B) | **current** |
| [`docs/archive/findings-v1-2026-04.md`](docs/archive/findings-v1-2026-04.md) | Archived v1 report | historical |
| [`docs/limitations.md`](docs/limitations.md) | Full caveats + the deviation log | **current** |
| [`docs/methodology.md`](docs/methodology.md) | Experiment design, evaluator, Policy A/B | v1-era core, still valid |
| [`docs/phase2-evaluator-taskbank-design.md`](docs/phase2-evaluator-taskbank-design.md) | Phase 2 task + evaluator design | **current** |
| [`docs/running-the-experiment.md`](docs/running-the-experiment.md) | Step-by-step replication guide | current |
| [`docs/conclusions.md`](docs/conclusions.md), [`docs/applying-findings.md`](docs/applying-findings.md), [`docs/tier-picker.md`](docs/tier-picker.md) | Analysis + practical-use guides | **v1-era — predate Phase 2** |
| [`docs/presentation/`](docs/presentation/) | Forwardable summaries (sv/en/research) | **v1-era — predate Phase 2** |
| [`DECISIONS.md`](DECISIONS.md) | Locked-in design decisions | current |

## Repo layout

```
.
├── docs/                     Methodology, findings, analysis, presentations
│   └── archive/              Frozen reports (v1, v2.1 implementation bank)
├── tasks/                    Frozen task banks — JSON specs + markdown prompts
│   ├── 001–008 …             Implementation bank
│   ├── 101–108 …             Review / hard-reasoning bank
│   └── ground-truth/         Committed answer keys for findings-scored tasks
├── mock-project/             PHP/Slim/Twig/Alpine/MariaDB target codebase
├── runner/                   Orchestrator (PHP 8.4 + PHPUnit)
│   ├── bin/cli               Main CLI: state init, pin-models, run-all, report
│   └── bin/cost-calculator.php   Cost forecaster for your own workload
├── docker/                   MariaDB init scripts
├── results/                  JSONL logs (append-only, gitignored at runtime)
├── state.json                Live experiment state (gitignored at runtime)
├── experiment_config.json    Frozen experiment parameters
├── DECISIONS.md              Design-decision log
└── LICENSE                   MIT
```

## Reproducing it

For a beginner-friendly walk-through see
[`docs/running-the-experiment.md`](docs/running-the-experiment.md). The
condensed flow:

```bash
docker compose up -d
cd mock-project && php tools/migrate.php && php tools/seed_demo.php && cd ..
cd runner && composer install && cd ..

php runner/bin/cli state init
php runner/bin/cli state pin-models \
  --haiku=claude-haiku-4-5-20251001 \
  --sonnet=claude-sonnet-5 \
  --opus=claude-opus-4-8 \
  --fable=claude-fable-5
php runner/bin/cli run-all
php runner/bin/cli report        # implementation bank; see note re: Phase 2
```

Which bank runs is set by `task_ids` / `tiers` in `experiment_config.json`.
Expect 10–30+ hours of wall-clock time — dispatches are gated by Anthropic
rate-limit windows, not compute.

## Reproducibility

The pipeline is deterministic end-to-end:

- `plan_seed=42` drives both dispatch order (seeded Fisher–Yates) and bootstrap
  sampling (1000 iterations).
- PHP dependencies pinned via `composer.lock`; the mock project is frozen at the
  tagged `scaffold_complete` ref; the task banks are frozen JSON.
- Pinned model IDs are recorded in `state.json` and verified per dispatch.
- Archived reports (`docs/archive/`) regenerate byte-for-byte from their source
  data given the same seed.

Anything non-reproducible (server-side model retuning under an alias, safeguard
routing) is documented in [`docs/limitations.md`](docs/limitations.md).

## Stack

- **Mock project:** PHP 8.4 + Slim 4 + Twig + Alpine.js + MariaDB 10.11
- **Runner:** PHP 8.4 + PHPUnit 11
- **Orchestrator:** Claude Code subscription (not the Anthropic API), invoked
  via `claude -p …` subprocess per dispatch, one pinned model per run
- **Container:** Docker Compose (MariaDB only)

## What this does NOT measure

- Code quality, maintainability, idiomatic style, or security-as-human-judgment
  — the evaluator is mechanical (tests / query count / precision-recall / rubric).
- Robustness to adversarial prompts or distractor instructions.
- Multi-session work, cross-repo coordination, or open-ended exploration.
- Sensitivity to prompt phrasing — each task has one frozen prompt.
- Real-codebase generalization — the mock project is synthetic with planted
  anti-patterns at a chosen density.

## License

MIT — see [`LICENSE`](LICENSE).
