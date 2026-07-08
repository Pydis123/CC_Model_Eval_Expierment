# Tier-fit in subagent dispatch: a controlled experiment on Claude Haiku, Sonnet, Opus, and Fable across implementation and review tasks

**Author:** Anders Hydén
**Date:** April 2026 (data recalibrated July 2026 — see Deviations, §5)
**Repo:** llm-dispatch-experiment (commit at run completion)

---

## TL;DR

- Two task banks, N=5 replicates, 4 pinned Claude tiers (Haiku, Sonnet,
  Opus, Fable) plus a pinned Opus judge for rubric-scored tasks.
- **Bank 1 (implementation, 8 tasks): a ceiling.** Every tier passed
  5/5 on every task (40/40 each). Tier choice is a cost decision, not
  a correctness one. Summed tokens for one full pass: Haiku ~97k ·
  Sonnet ~156k · Opus ~163k · Fable ~162k — Haiku ~40% cheaper than
  Sonnet or Opus for an identical result.
- **Bank 2 (review & hard reasoning, 8 tasks): tiers separate.** Haiku
  nails mechanical review (seeded-defect finding 5/5, PR code review
  5/5) but fails reasoning-heavy review (plan-review 2/5, query-budget
  analysis 2/5). Sonnet is the workhorse: 5/5 on 7 of 8 review tasks.
  Opus is the only flawless tier (40/40) but was never the sole
  passer. Fable matches Sonnet on cost and quality where it runs, but
  its dual-use safeguards silently reroute security-adjacent
  dispatches (100% on the security-audit task).
- Escalation (Haiku → Sonnet → Opus, on failure only) remains the
  cheapest reliability-oriented strategy in expectation, roughly 35%
  under all-Opus; skipping Sonnet (Haiku → Opus) is within ~5–10% and
  is a simpler mental model.
- Prompt specificity remains the dominant cost lever — bigger than
  tier choice.

---

## Abstract

We measure pass rate and token cost for four Claude model tiers
(Haiku, Sonnet, Opus, Fable) deployed as subagents under a
project-manager dispatch architecture, across two task banks in a
PHP/Slim mock project: an eight-task **implementation bank** (explicit
edits against named patterns) and an eight-task **review & hard
reasoning bank** (plan review, seeded-defect finding, PR review,
architecture-decision memos, a no-repro bugfix, a transactional
refactor, and query-budget analysis). Each (task, tier) cell targets
N=5 replicates. The implementation bank is evaluated mechanically
(test pass, query budget, file-exists, diff-size); the review bank
adds two further evaluator families — findings scored by
precision/recall against a committed ground-truth key, and rubric
memos scored 0/1/2 by a pinned Opus judge.

The implementation bank is a **ceiling**: all four tiers pass every
task (40/40 each), and tier choice affects only token cost (Haiku
~40% cheaper than Sonnet or Opus for the same outcome). The review
bank is where tiers separate: Haiku fails reasoning-heavy review
(plan-review 2/5, query-budget/N+1 analysis 2/5) while still matching
the ceiling on mechanical review tasks; Sonnet is the workhorse
(5/5 on 7 of 8 review tasks); Opus is the only tier with zero failures
across the review bank (40/40) but is never the *sole* passer on any
task; Fable ties Sonnet on cost and quality wherever it completes a
clean run, but its dual-use safeguards silently reroute
security-adjacent dispatches, most severely on the seeded-defect
security-audit task (100% rerouted, zero usable observations).
Findings are bounded by N=5 sample sizes, a synthetic mock project,
one frozen prompt per task, and — for the review bank — a judge model
that shares an identity with one of the tiers under test. A dedicated
deviations section documents an earlier, discarded dataset generation
and the current bank's known incompleteness.

## 1. Introduction

Anthropic's Claude API exposes multiple model tiers — Haiku, Sonnet,
Opus, and (in this environment) Fable — at substantially different
price points. Practitioners building agentic systems repeatedly face
the question: *which tier should this task go to?* The published
model-card distinctions ("Haiku is fast and cheap; Opus is the most
capable") are accurate but operationally vague, and they say nothing
about how tier-fit differs between well-specified implementation work
and open-ended review or reasoning work.

This experiment makes the tradeoff concrete for two realistic
scenarios: small-to-medium coding tasks, and review/analysis tasks
(plan critique, defect finding, architecture memos, root-cause
debugging), both dispatched to a subagent under project-manager
orchestration. The research question is:

> Given a task in a familiar codebase, what does each tier cost in
> tokens, how reliably does it produce a correct result, and does the
> answer change between "write this code" and "judge this code /
> reason about this tradeoff"?

We do not attempt to measure code quality or behavior on adversarial
inputs beyond what the review bank's evaluators capture. The goal is
to surface tier-fit patterns that practitioners can apply to dispatch
decisions in their own projects, with stated bounds on
generalizability.

## 2. Methodology

Full methodology is documented in `docs/methodology.md`. Summary:

### 2.1 Task banks

Two task banks were authored in advance and frozen before dispatch.

**Bank 1 — implementation (tasks 001–008, internally "v2.1").** Task
shapes commonly seen in PHP web development:

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

**Bank 2 — review & hard reasoning (tasks 101–108, internally "Phase
2")**, added to test whether the implementation bank's tier-fit
conclusions extend to review and analysis work:

| ID | Category | Description |
|---|---|---|
| 101 | plan_review | Adversarial review of a proposed implementation plan |
| 102 | security_audit | Find seeded defects in a codebase (ground-truth key) |
| 103 | code_review | Review a PR diff |
| 104 | multi_tenancy | Architecture-decision memo |
| 105 | webhook_delivery | Architecture-decision memo |
| 106 | bug_no_repro | Root-cause a bug with no working reproduction |
| 107 | transactional_refactor | Refactor for transactional correctness |
| 108 | query_budget_perf | Query-budget / N+1 performance analysis |

Each task has a markdown prompt and a JSON or rubric specification of
evaluator checks. Tasks were not modified after dispatch began.

### 2.2 Tiers

Four Claude model tiers were used, plus a separate judge role. All
five are pinned to exact, dated model identifiers and verified per
dispatch (no aliases):

- **Haiku:** `claude-haiku-4-5-20251001`
- **Sonnet:** `claude-sonnet-5`
- **Opus:** `claude-opus-4-8`
- **Fable:** `claude-fable-5`
- **Rubric/triage judge (Bank 2 only):** `claude-opus-4-8` — pinned
  separately from, but identical in underlying model to, the Opus tier
  under test. See §6 for the validity implication.

### 2.3 Dispatch protocol

**Bank 1 (mechanical evaluation)** and the mechanically-evaluated Bank
2 tasks (106, 107, 108) follow the original worktree protocol:

1. Creates a fresh git worktree of the mock project from a frozen base
   ref.
2. Installs Composer dependencies in the worktree (gitignored).
3. Invokes the subagent with a fixed allowlist of tools (Bash, Edit,
   Read, Write, Glob, Grep) at the pinned model ID.
4. Runs the evaluator against the resulting worktree.
5. If failed, includes the failing-check summary in the prompt for
   the next iteration, up to a bounded retry budget.
6. Records the run as `passed` or `failed` and appends one JSONL row
   to `results/results.jsonl`.

**Bank 2's findings-scored and rubric-scored tasks (101–105)** run
under a stricter answer-key isolation protocol, because the model
must not be able to see or recover the ground truth it is being
scored against:

- Each run executes in a **non-linked `git archive` export** of the
  mock project, so the ground-truth answer key is not reachable via
  the git object database (a plain worktree prune does *not* prevent
  `git show <ref>:path` access — this was corrected for Bank 2).
- Transcripts are scanned by a contamination detector after the run.
- One residual leak vector — an absolute filesystem path that could
  hint at the key's location — is detected and discarded rather than
  structurally sandboxed; see §6.

### 2.4 Evaluator families

Three evaluator families are used, deterministic and with no human in
the loop:

1. **Mechanical** (Bank 1 in full, plus Bank 2 tasks 106/107/108):
   PHPUnit subsets, full smoke suite, MariaDB query-count under a
   budget, file-exists, regex-absent, diff-line-cap, and — for the
   no-repro bugfix (106) — a red/green regression gate requiring the
   new test to FAIL on the buggy baseline and PASS on the fix.
2. **Findings-scored** (101, 102, 103): the model emits a
   `findings.json`, scored by precision and recall against a
   **committed** ground-truth answer key (`tasks/ground-truth/`).
   Pass requires both recall and precision to clear thresholds — the
   model must find the planted issues without hallucinating many false
   ones.
3. **Rubric-scored** (104, 105): the model emits a decision memo,
   scored by the pinned Opus judge against an anchored 0/1/2 rubric
   with a pass threshold.

### 2.5 Replication and ordering

Each (task, tier) cell targets **N=5** replicates in both banks (up
from N=3 in the original single-bank design), nominally 8 × 4 × 5 =
160 runs per bank. Dispatch order within each bank follows the same
deterministic seeded-shuffle scheme as the original harness design.

### 2.6 Escalation policy

**Retry-only** (within a single tier) is what both banks actually ran:
a failed run retries on the same tier up to the harness's bounded
retry budget before being recorded as a failure for that cell.

**Escalation across tiers** (Haiku → Sonnet → Opus, on failure only,
capped at two same-tier attempts before escalating) is not run as a
separate live policy in this recalibration; its cost advantage is
carried forward from the original bootstrap-simulation methodology and
re-examined qualitatively against the two banks' actual pass-rate
data in §3.6 and §4.3. We did not recompute a fresh bootstrap
confidence interval for the two-bank data; the ~35%-under-all-Opus
figure is the standing economic conclusion, not a number newly fit to
Bank 1 or Bank 2 in isolation.

## 3. Results

### 3.1 Bank 1 — implementation pass rates (ceiling)

| Tier | Passed | Failed | Pass rate |
|---|:---:|:---:|:---:|
| Haiku  | 40 | 0 | 100.0% |
| Sonnet | 40 | 0 | 100.0% |
| Opus   | 40 | 0 | 100.0% |
| Fable  | 40 | 0 | 100.0% |

Every tier passed every one of the 8 implementation tasks across all 5
replicates. There is no pass-rate signal left to analyze in this
bank — the only remaining axis of comparison is cost.

### 3.2 Bank 1 — cost

Summed across all 8 tasks, mean tokens for one full pass:

| Tier | Summed tokens (approx.) | Ratio to Haiku |
|---|---:|---:|
| Haiku  | ~97,000  | 1.00× |
| Sonnet | ~156,000 | ~1.61× |
| Opus   | ~163,000 | ~1.68× |
| Fable  | ~162,000 | ~1.67× |

Haiku is roughly 40% cheaper than Sonnet or Opus for an identical
outcome across the bank. This recalibration reports the bank-level
summed-token comparison rather than a re-derived per-task
tokens/wall-clock table: the Phase-2 hand-aggregation this document
draws on (see §5c) did not reconstruct per-task Bank 1 breakdowns, and
restating fabricated per-task figures would misrepresent the
underlying data. Readers who need the per-task Bank 1 table should
consult `docs/archive/findings-v2.1.md`, where it is generated
directly from `results/results.jsonl`.

### 3.3 Bank 2 — review & hard-reasoning pass rates

Pass rate = clean runs that passed / clean runs (a "clean" run is one
that was not silently rerouted by a safeguard or blocked by a
provider-side rate cap; see §3.5).

| Task | Haiku | Sonnet | Opus | Fable | Fable interference |
|---|---|---|---|---|---|
| 101 plan-review (adversarial) | 2/5 | 5/5 | 5/5 | 5/5 | 0% |
| 102 security-audit (seeded defects) | 5/5 | 5/5 | 5/5 | N=0 | 100% rerouted |
| 103 code-review (PR diff) | 5/5 | 3/5 | 5/5 | 4/4 | 20% rerouted |
| 104 multi-tenancy (arch decision) | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 105 webhook-delivery (arch decision) | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 106 bug-no-repro | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 107 transactional-refactor | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| 108 query-budget-perf | 2/5 | 5/5 | 5/5 | 3/3 | 0% |

Haiku's two failure cells (101, 108) are both reasoning-heavy: judging
an adversarial plan, and reasoning about a query budget distributed
across call sites. Its two mechanical-review cells (102, 103) are both
5/5. Sonnet's only dip is 103 (code review, 3/5). Opus has zero
failures anywhere in the bank.

### 3.4 Bank 2 — cost

| Tier | Mean tokens/run (approx.) | Ratio to Haiku |
|---|---:|---:|
| Haiku  | ~17,000 | 1.00× |
| Sonnet | ~20,000 | ~1.18× |
| Opus   | ~23,000 | ~1.35× |
| Fable  | ~20,000 | ~1.18× |

Per-run cost separation in Bank 2 is much narrower than the ~1.6–1.7×
spread seen in Bank 1's summed totals — consistent with Bank 2's
single-shot dispatch protocol for the findings- and rubric-scored
tasks versus Bank 1's iterative retry-with-feedback loop.

### 3.5 Fable's incomplete cells

Three Bank 2 cells did not reach full N for Fable:

- **102 (security-audit): N=0/5.** All five dispatches were silently
  rerouted by Fable's dual-use safeguards before producing a usable
  `findings.json`. This is not missing data in the ordinary sense — it
  *is* the measurement (Fable declines security-adjacent work).
- **103 (code-review): N=4/5.** One dispatch rerouted; the four clean
  runs all passed.
- **108 (query-budget-perf): N=3/5.** Two dispatches were blocked by a
  Fable weekly usage cap (a provider-side rate limit, not a safeguard
  reroute — interference is correctly recorded as 0% for this row);
  the three clean runs all passed.

See §5a for why this incompleteness does not change any headline
conclusion.

### 3.6 Escalation economics

The standing conclusion from the original bootstrap-simulation
methodology — that Haiku → Sonnet → Opus escalation (retrying on
failure only, never "to be safe") is the cheapest strategy in
expectation for reaching near-100% reliability, roughly 35% under an
all-Opus policy, with Haiku → Opus (skipping Sonnet) within ~5–10% of
that — is consistent with both banks' pass-rate data: Bank 1 needs no
escalation at all (any tier alone already clears every task), and
Bank 2's failures are concentrated on exactly two Haiku cells that
Sonnet fully covers, which is the shape the escalation argument
depends on. We do not report a freshly recomputed bootstrap
confidence interval for the two-bank data in this document (see §2.6).

## 4. Discussion

### 4.1 Implementation is a solved dispatch problem

All four tiers cleared every implementation task in every replicate.
The structural commonality across Bank 1's eight categories — local
change scope, mechanically testable success criteria, a plan
expressible as a sequence of explicit edits, conventional codebase
patterns — appears to be sufficient for any current tier to succeed.
For this class of work, paying for a higher tier purchases capability
headroom that the task does not exercise: Opus costs ~68% more tokens
than Haiku for a result Haiku already achieves cleanly. **Tier
selection for well-specified implementation work is a pure cost
decision.**

### 4.2 Where tiers separate: review and hard reasoning

Bank 2 is where the experiment's central finding lives. The failure
pattern is not "Haiku is worse at review" in general — it is worse at
a specific *kind* of review. Haiku matches the ceiling on:

- 102, seeded-defect finding — spotting planted issues against a known
  taxonomy is closer to pattern-matching than open-ended judgment.
- 103, PR code review — reviewing a bounded diff against visible
  context.

Haiku fails on:

- 101, adversarial plan review — the task requires holding a proposed
  plan against unstated risks and pushing back, not just checking it
  against a checklist.
- 108, query-budget/N+1 analysis — reasoning about a budget distributed
  across call sites that the prompt does not enumerate.

The dividing line looks like *bounded pattern-matching against
something a checklist can capture* versus *open-ended reasoning that
requires generating the checklist itself*. This reframes and narrows
the old, now-retired belief that "N+1 needs Opus" — Sonnet fully
closes that gap (5/5 on 108); the actual rule is "Haiku is
insufficient for reasoning-heavy review; the floor is Sonnet," not
"only Opus can do it."

### 4.3 Sonnet as the review workhorse

Sonnet went 5/5 on 7 of the 8 review tasks, including both tasks where
Haiku failed. Its single dip — 103, code review, 3/5 — does not
correlate with the reasoning-heavy/mechanical split that explains
Haiku's failures; on doubt for code-review specifically, escalate to
Opus. On implementation, Sonnet never beat Haiku's pass rate (both are
at the ceiling) while costing ~60% more tokens summed across the
bank — there is no implementation-bank case for defaulting to Sonnet.
Its role is squarely: the default for review/analysis and
query-budget/N+1 reasoning, and the escalation tier when Haiku fails
on implementation.

### 4.4 Opus: flawless, but never required alone

Opus is the only tier with zero failures across the full Bank 2 grid
(40/40). But on 7 of the 8 review tasks, Sonnet matched it at lower
token cost, and on the 8th (103) Haiku matched it too. No single task
in either bank *required* Opus — every task that Opus passed was also
passed by at least one cheaper tier. Its measured value in this
dataset is **reliability without task-specific knowledge**: if a
dispatcher cannot characterize a task well enough to know whether it's
mechanical or reasoning-heavy, Opus is the safe default and the
correct top rung of an escalation chain. That is a different claim
from "Opus is needed for correctness," and the data supports only the
former. Categories where Opus's distinct value may genuinely be
required — security review as human judgment (rather than seeded-defect
finding), architecture decisions beyond the two rubric tasks measured
here, cross-system debugging, multi-service transaction reasoning at
scale, PM/orchestration — remain unmeasured (§5d).

### 4.5 Fable: no dispatch case, and a safeguard hazard

Where Fable completed clean runs, it matched Sonnet on both cost
(~1.18× Haiku, same as Sonnet) and pass rate — it offers no measured
advantage over Sonnet anywhere in either bank. It does carry a
measured *liability*: its dual-use safeguards silently reroute
security-adjacent dispatches before they produce usable output. This
was total on the seeded-defect security-audit task (102: 100%
rerouted, N=0 usable observations) and partial on PR code review (103:
20% rerouted). Because Sonnet dominates Fable on cost, quality, and
reroute risk simultaneously, there is no configuration in which Fable
is the right choice over Sonnet in this dataset. **Hard rule: never
route security-audit, security-review, or adversarial code review to
Fable** — the failure mode is silent (no error, no findings, no
signal that the dispatch didn't happen as intended).

### 4.6 Prompt specificity as a cost lever

Consistent with the original single-bank finding, the biggest lever
this experiment observed is not tier choice but prompt specificity.
Bank 2's own tier-separation reinforces this from a different angle:
Haiku's two failure cells (101, 108) are exactly the two tasks whose
success criteria cannot be fully pre-specified in the prompt — an
adversarial plan review and a call-site-distributed query budget both
require the model to *discover* what to check, not just execute a
named check. A ~50-word addition naming the specific pattern, the
existing code to compare against, or the exact test to write can
plausibly move a task from "needs Sonnet" to "Haiku is fine" — this
remains a structural argument rather than a controlled ablation (see
Future work, §7).

### 4.7 Practical configuration

The findings can be operationalized through tier-routing rules
encoded in a project's or user's `CLAUDE.md` instructions. A minimal,
corrected operationalization is:

```markdown
## Model selection for coding tasks

Pick the cheapest tier with a reasonable chance of solving the task.
Escalate on failure; do not retry the same tier more than twice.

- Haiku: default for ALL well-specified implementation work
  (i18n, migrations with explicit plans, route+RBAC, frontend wiring,
  documented refactors, bugfixes with reproductions). Also fine for
  mechanical review: seeded-defect finding, bounded PR code review.
  NOT for adversarial plan review or query-budget/N+1 reasoning —
  escalate those straight to Sonnet.
- Sonnet: the workhorse for review and analysis, and the escalation
  tier when Haiku fails on implementation. Escalate to Opus on doubt
  for code review specifically.
- Opus: the blind-safe default and top escalation rung when a task
  cannot be characterized well enough to route confidently, or for
  categories not covered by this experiment (security review as
  judgment, cross-system debugging, large-scale multi-service
  transactions, architecture beyond a bounded decision memo).
- Never route security-audit, security-review, or adversarial code
  review to Fable — its safeguards silently reroute the dispatch.
```

This rule set encodes §4.1–4.5. A more elaborate version covering
PM-orchestrated dispatch, cost-bounded projects, and prompt
engineering investments is provided in the repository's
`docs/applying-findings.md`. The included `cost-calculator.php`
permits forecasting monthly spend under multiple strategies for a
user-supplied workload mix; see that tool for project-specific
projections rather than the single-mix numbers reported in the
original single-bank version of this study.

## 5. Deviations & Threats to Validity

This section documents, without hedging, the ways this recalibration's
process deviated from a clean, complete, from-scratch run, and argues
for or against each deviation's effect on the headline conclusions.

### 5a. Fable's incomplete Bank 2 cells

Fable did not reach full N=5 in three Bank 2 cells (102: N=0, 103:
N=4, 108: N=3; see §3.5). This does not change any conclusion in this
document. Fable was never the sole passer on any task in either bank
and tracked Sonnet on both cost and quality wherever it did run
cleanly; the tier-separation story in Bank 2 is carried entirely by
the fully-sampled Haiku/Sonnet/Opus columns. On 102, the missing data
*is* the finding — a full N=5 re-run would only re-confirm that
Fable's safeguards block the dispatch; the quantity "Fable's clean
pass rate on a security-audit task" is unmeasurable by construction
under Fable's current safeguard behavior, not merely under-sampled.
The incompleteness widens Fable's confidence intervals on 103 and 108;
it does not flip either cell's verdict, since the clean runs that did
occur match the ceiling other tiers also reached.

### 5b. The discarded v2.0 implementation dataset

An earlier implementation-bank dataset generation ("v2.0") was
discarded due to two harness confounds discovered after the fact: a
**diff-trap**, where a worktree-prune step left infrastructure files
as unstaged deletions that the diff-size evaluator check
miscounted as part of the subagent's diff; and **operator-context
leakage**, where dispatched subagent sessions could read the
operator's personal `CLAUDE.md`, which at the time included a table
describing each model tier's own expected scope — a direct
contamination of the independent variable. Both were structural bugs
in the harness, not in the tasks or the tiers. The dataset was
discarded rather than corrected retroactively, and the bank was
re-run clean as "v2.1" in a sanitized environment with the leaks
closed. The v2.0 data is retained in the repository as a measurement
of the harness confound itself, not used in any conclusion in this
document.

### 5c. The Phase-2 report generator gap

The automated `report` generator that produces `findings.md` for the
implementation bank cannot yet regenerate an equivalent document for
Bank 2: it hard-requires N=5 in every cell and has no handling for
safeguard-rerouted runs, which it would currently miscount as
ordinary passes or failures rather than excluding them as non-clean.
The Bank 2 numbers in this document were therefore **hand-aggregated**
directly from `results/results.jsonl` with zero model dispatch
involved in the aggregation step itself. The implementation bank's
automated report remains valid and is committed at
`docs/archive/findings-v2.1.md`; Bank 2 has no equivalent generated
artifact as of this recalibration.

### 5d. What remains unmeasured

Several categories referenced qualitatively in §4.4 and §4.7 have not
been measured in either bank and should not be read as validated by
this experiment: security review as human judgment (as distinct from
seeded-defect *finding*, which is measured), architecture decisions
beyond the two rubric-scored memo tasks (104, 105), cross-system
debugging, multi-service transaction reasoning at production scale,
PM/orchestration work, and multi-session or cross-repository work.
Opus's presumed value in these categories is a reasoned extrapolation
from its measured reliability-without-task-knowledge property (§4.4),
not a direct finding.

### 5e. Standing sample-size and reproducibility caveats

N=5 per cell is larger than the original N=3 but is still small
relative to what would be needed for narrow confidence intervals —
cross-task patterns (e.g., "Haiku fails reasoning-heavy review, not
review in general") should be trusted over any single cell's exact
fraction. Each task has exactly one frozen prompt; no
adversarial-prompt or phrasing-robustness variant was tested. The mock
project is synthetic, with planted patterns and anti-patterns at a
known density that may not match a real codebase's baseline. Finally,
Fable's safeguard-reroute behavior is itself a source of
non-reproducibility: whether a given dispatch is rerouted is not fully
deterministic from the prompt text alone, which is why §5a treats
Fable's incomplete cells as a structural property of the tier rather
than a sampling accident to be closed by more runs.

## 6. Limitations

These limitations are detailed further in `docs/limitations.md`.
Summary, focused on points not already covered in §5:

- **Mechanical + findings + rubric evaluators, not human judgment.**
  Bank 2 substantially widens what is measured beyond Bank 1's purely
  mechanical checks, but the findings-scored and rubric-scored
  families still reduce "was this a good review/decision" to a
  precision/recall score against a fixed key, or a 0/1/2 rubric score
  from a single judge model. Neither substitutes for expert human
  review of the underlying reasoning quality.
- **The rubric judge shares an identity with the top tier under
  test.** The Bank 2 judge is pinned to `claude-opus-4-8` — the same
  dated model ID as the Opus tier being evaluated on tasks 104 and
  105. This is a structural risk of self-preference bias (a model
  judging outputs from its own family more favorably) that this
  recalibration does not rule out; it is a candidate explanation, not
  a confirmed cause, for any part of Opus's flawless Bank 2 record on
  the rubric-scored tasks specifically.
- **Single execution environment.** All runs occurred on one
  developer's machine and against one hosted API surface; timing and
  rate-limit behavior (e.g., Fable's weekly usage cap, §3.5) reflect
  that environment and account and may not generalize.
- **Answer-key isolation has one accepted residual vector.** The
  non-linked git-archive export plus contamination detector close the
  main leak paths for Bank 2's findings- and rubric-scored tasks, but
  one vector — an absolute filesystem path visible in a transcript —
  is detected and discarded rather than structurally prevented (§2.3).
- **Token accounting excludes PM/orchestration overhead** in both
  banks, consistent with the original single-bank design.

## 7. Future work

- **Prompt-specificity ablation.** Same task, several prompt variants
  (terse / structured / structured+example / structured+example+test)
  on the same tier, to quantify the lever argued for qualitatively in
  §4.6 rather than only structurally.
- **Close the report-generator gap (§5c).** Extend the automated
  report tool to handle variable N and safeguard-rerouted runs so Bank
  2 can produce a generated, diffable report artifact rather than a
  hand-aggregated one.
- **Measure the genuinely-unmeasured Opus-specific categories (§5d).**
  Security review as judgment, architecture beyond two rubric tasks,
  cross-system debugging, multi-service transactions at scale, and
  PM/orchestration work are all plausible Opus-favoring categories
  that this experiment has not yet tested.
- **Judge-independence check.** Re-score a sample of Bank 2's
  rubric-scored runs with a judge model that does not share an
  identity with any tier under test, to bound the self-preference risk
  flagged in §6.
- **Real-codebase replication.** Extending the methodology to one or
  more real, non-synthetic codebases, for both banks, would test
  whether the mock-project findings generalize.
- **Larger N.** Beyond N=5, particularly for Fable's incomplete cells
  where the safeguard/rate-cap behavior allows it (§5a) — noting that
  the security-audit cell is expected to remain unmeasurable by
  construction regardless of N.
- **Multi-session and cross-repository work.** Both banks bound each
  task to a single dispatch and a single repository; replicating with
  longer-horizon, multi-session, or cross-repo tasks would test tier
  behavior outside that regime.

## 8. Reproducibility

The experiment is deterministically reproducible end-to-end within
each bank:

- Dispatch order within a bank follows a deterministic seeded shuffle,
  unchanged in mechanism from the original harness design.
- All PHP dependencies are pinned via `composer.lock`.
- The mock project is a frozen git ref; Bank 2's findings/rubric tasks
  additionally isolate the ground-truth key via a non-linked
  `git archive` export (§2.3).
- Both task banks are frozen.
- Model tiers and the rubric judge are pinned to exact, dated model
  IDs (§2.2) and verified per dispatch.

To reproduce:

```
docker compose up -d
cd mock-project && php tools/migrate.php && php tools/seed_demo.php
cd ../runner && composer install
cd ..
php runner/bin/cli state init
php runner/bin/cli state pin-models \
  --haiku=<id> --sonnet=<id> --opus=<id> --fable=<id> --judge=<id>
php runner/bin/cli run-all
php runner/bin/cli report   # regenerates the Bank 1 report only, see §5c
```

Bank 1's automated report is diffable against
`docs/archive/findings-v2.1.md`. Bank 2 has no automated report as of
this recalibration; its canonical numbers are the hand-aggregation
from `results/results.jsonl` reported in this document, and the
current public summary lives in `README.md`. Anything non-deterministic
— server-side model behavior, Fable's safeguard routing, and
provider-side rate caps — is documented in `limitations.md` and §5 of
this document.

## 9. Conclusion

Across two task banks — implementation and review/hard-reasoning —
under controlled dispatch, tier-fit is not a single story. For
well-specified implementation work, tier choice is now measured as a
**ceiling**: all four tiers pass every task, and the only remaining
decision is cost, where Haiku is roughly 40% cheaper than Sonnet or
Opus for an identical outcome. For review and hard reasoning, tiers
genuinely separate: Haiku is sufficient for mechanical review
(seeded-defect finding, bounded PR review) but not for reasoning-heavy
review (adversarial plan critique, query-budget analysis distributed
across call sites), where Sonnet is the reliable floor. Opus is the
only tier with zero failures across the review bank, but no task in
either bank required it exclusively — its measured value is
reliability without task-specific knowledge, making it the correct
blind-safe default and top escalation rung rather than a correctness
requirement. Fable offers no measured advantage over Sonnet anywhere
and carries a distinct, measured liability: its safeguards silently
reroute security-adjacent dispatches, up to total rerouting on a
seeded-defect security-audit task. An escalation chain
(Haiku → Sonnet → Opus, on failure only) remains the cheapest
reliability-oriented strategy in expectation, and prompt specificity
remains a bigger lever than tier choice.

The findings should not be over-generalized. The experiment is bounded
by N=5 per cell, a synthetic mock project, one frozen prompt per task,
a review-bank judge that shares an identity with one of the tiers
under test, and — for Fable specifically — three incompletely sampled
cells whose incompleteness is argued, not merely asserted, not to
change any headline conclusion (§5a). Several categories where Opus's
distinct value is plausible remain entirely unmeasured (§5d). Future
replications with larger N, real codebases, an independent judge
model, and a controlled prompt-specificity ablation are needed to
strengthen these conclusions further.

## Acknowledgments

The runner and analysis pipeline were built collaboratively with
Claude (orchestrator: Opus 4.8; subagents and tiers under test: Haiku
4.5, Sonnet 5, Opus 4.8, Fable 5). An earlier implementation dataset
generation was discarded and re-run after two harness confounds were
found (§5b); that discovery and fix, and the Bank 2 hand-aggregation
workaround for the report generator (§5c), are documented as part of
the experiment record in `docs/limitations.md`.
