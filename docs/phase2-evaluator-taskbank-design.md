# Phase 2 — Evaluator and Task Bank Design Spec

**Date:** 2026-07-06
**Status:** Approved by Anders (2026-07-06). Design only — NO runs are
authorized by this spec; the campaign start is a separate budget decision.
**Parent spec:** the v2 recalibration design (approved 2026-07-03; maintained
as a local process document outside version control). This spec concretizes
its Phase 2 section to buildable level, restates the decisions it depends on,
and records two revisions (judge model, ODB isolation mechanism).

---

## Decisions recorded in this spec

1. **Scale: 160 runs (8 tasks × 4 tiers × N=5)** — confirmed by Anders
   2026-07-06. Matches Phase 1/v2.1 methodology; no top-up plumbing needed.
   Campaign starts at a weekly rate-window reset rather than being slimmed.
2. **Judge model: `claude-opus-4-8`** — REVISES the parent spec's
   `claude-fable-5` decision, approved by Anders 2026-07-06. Rationale: the
   judge triages SQLi/XSS/vulnerability findings, the most safeguard-exposed
   content in the experiment; v2.1 observed a live `model_rerouted` disposition
   even on an innocuous i18n implementation prompt (run `a1037ea91a50`). A
   judge that reroutes or refuses mid-scoring corrupts the measurement
   instrument with the same confound the experiment measures in its subjects.
   Switching to Opus 4.8 also eliminates Fable self-preference bias when
   judging Fable outputs. Cost: a somewhat weaker judge; mitigated by
   mechanical-first matching (the judge only triages unmatched findings) and
   anchored 0/1/2 rubrics.
3. **ODB isolation: option (b) from the parent spec, strengthened** — export
   `mock-project/` via `git archive` into the run dir, then `git init` a fresh
   repo with a single `baseline` commit. Chosen over (a) separate ground-truth
   repo (solves only ground truth, not `phase2-audit-target` history or
   `../tasks` reachability) and (c) stripping `.git` access (access control
   against a Bash-capable agent is an arms race; (b) removes the objects
   instead of hiding them).

## Task bank (ids 101–108)

| # | Task | Category | Iter | Artifact | Evaluator |
|---|---|---|---|---|---|
| 101 | Adversarial review of a ~300-line implementation plan with ~8 seeded flaws | `plan_review` | 1 | `findings.json` | findings P/R |
| 102 | Security audit of defect-seeded codebase (~12 seeded vulns) | `security_audit` | 1 | `findings.json` | findings P/R |
| 103 | Code review of a prepared PR with ~8 seeded bugs | `code_review` | 1 | `findings.json` | findings P/R |
| 104 | Multi-tenancy schema/architecture decision memo | `architecture_decision` | 1 | `decision-memo.md` | rubric panel |
| 105 | Webhook delivery subsystem decision memo (ordering, at-least-once, retry/poison) | `architecture_decision` | 1 | `decision-memo.md` | rubric panel |
| 106 | Hard bug, no repro: "reopened tickets sometimes lose SLA deadline" (seeded cross-service race) | `bugfix_no_repro` | 3 | code + regression test | red/green mechanical |
| 107 | Hard implementation: cross-service transactional refactor | `impl_hard` | 3 | code | phpunit + smoke + diff budget |
| 108 | Hard implementation: performance fix under strict query budget + tight diff limit | `impl_hard` | 3 | code | phpunit + query_count + diff limit |

Per-task notes:

- **101:** the plan ships as `PLAN.md` in the run dir and is written against
  the real codebase/schema; flaws are of the form "the plan writes X, the
  database has Y". Finding them requires reading the code — that is the
  query-budget-reasoning axis being measured. Seeded flaw classes (from the
  parent spec): schema mismatch vs real DB, missing index, breaks existing
  UNIQUE constraint, API contract break, missed authz, un-failable test, race
  in backfill, wrong rollback.
- **102:** runs against ref `phase2-audit-target` — the mock project with ~12
  seeded vulnerabilities squashed into one innocuous commit. Vuln classes
  (parent spec): SQLi, IDOR, XSS via `|raw`, CSRF gap on a state-changing
  route, session fixation, timing-unsafe compare, secret logging, missing
  authz, race, transaction gap. This task is the epicenter of the Fable
  safeguard confound; disposition is recorded as a first-class finding.
- **103:** the PR is applied as commit `review-target` on top of `baseline` in
  the run dir's fresh git repo, so the agent can use
  `git diff baseline..review-target` naturally, like a real PR review. Seeded
  bug classes: logic, off-by-one, N+1 regression, broken invariant, injection.
- **104–105:** memos are written against the real codebase (existing schema,
  existing service boundaries), not in a vacuum — otherwise the experiment
  measures generic architecture knowledge every tier has from training.
- **Single-shot rule:** 101–105 run `max_iterations=1`. Retry feedback would
  leak ground truth, and real review dispatches are single-shot. 106–108 keep
  Policy A (3 iterations); their failed-check feedback is mechanical and
  leak-free.

## Findings evaluator — `findings_score` check (101–103)

**Output contract** (identical for all tiers; equal format burden is the
fairness property):

```json
{"findings": [{"file": "src/...", "line": 42, "defect_class": "sqli", "explanation": "..."}]}
```

- `defect_class` comes from a **frozen taxonomy** (~20 classes): `sqli`,
  `xss`, `csrf_gap`, `idor`, `authz_missing`, `race_condition`, `n_plus_one`,
  `logic_error`, `off_by_one`, `api_contract_break`, `constraint_break`,
  `missing_index`, `unsafe_compare`, `secret_logging`, `session_fixation`,
  `transaction_gap`, `rollback_wrong`, `test_unfailable`, `backfill_race`,
  `other`. The taxonomy is included in the task prompt and shared with the
  ground-truth files — that is what makes matching mechanical. Exact list is
  frozen at authoring time together with the ground truth.
- **Cap: 25 findings per run**, stated in the prompt. The precision threshold
  punishes shotgunning naturally; the cap bounds triage cost.
- The artifact is written to a fixed path named in the prompt
  (`findings.json` in the agent cwd); the evaluator reads it from the run dir.

**Mechanical matcher** against `tasks/ground-truth/<task-id>.json`:

1. Match = same file + same `defect_class` + (line within ±15 **or** same
   enclosing function). The enclosing function is resolved from the
   ground-truth side at authoring time (each seeded defect records its
   file + function name + line span); the matcher compares the finding's
   line against the recorded span — no AST parsing at evaluation time.
   Non-code files (templates, SQL, config) use the ±15-line rule only.
2. Greedy one-to-one: each seeded defect matches at most once; additional
   findings hitting an already-matched defect count as `duplicate`, not false
   positive.
3. Unmatched findings go to **judge triage** (pinned `claude-opus-4-8`):
   verdict ∈ `real_unseeded` / `duplicate` / `hallucination`. Verdicts are
   logged separately so the pure-mechanical score is always recoverable.

**Metrics per run** (logged in the results row `metrics` object): `recall`
(mechanical, against seeded defects), `precision_mechanical` (TP / total),
`precision_adjusted` ((TP + real_unseeded) / total — genuine unseeded findings
are not penalized), `f1`, raw counts (`true_positives`, `false_positives`,
`duplicates`, `hallucinations`), `judge_verdicts[]`.

**Binary `passed`** (pipeline compatibility): `recall ≥ r_min` AND
`precision_adjusted ≥ p_min`, thresholds per task in the task JSON. Starting
point recall ≥ 0.5 and precision ≥ 0.6; calibrated against the detectability
proofs during authoring and frozen before any run.

## Rubric evaluator — `rubric_score` check (104–105)

- **Anchored rubric:** 8–12 criteria per task with concrete 0/1/2 descriptors
  (e.g. "identifies the tenant-id backfill problem: 0 = absent, 1 = mentioned,
  2 = analyzed with mitigation"). Criteria are authored against the codebase's
  actual pitfalls and frozen before any run.
- **Absolute scoring, one memo per judge call** — no comparative ranking, so
  ordering effects vanish. Blinding: tier identity stripped; the prompt
  instructs agents not to sign; the runner defensively strips lines matching
  model names.
- **3 independent judge calls** per memo, median per criterion, sum = score.
  `passed` = score ≥ task threshold. Raw per-criterion scores logged.
- Judge calls go through the same `ProcessClaudeCli` with the pinned judge id.
  Malformed judge JSON → one retry, then an error mark (never a silent guess).

## Red/green evaluator — `regression_red_green` check (106)

The agent must deliver a fix plus a regression test. The evaluator:
(1) identifies new/changed test files via diff against `baseline`;
(2) copies them onto a fresh export of `baseline` and runs them there — MUST
fail; (3) runs them in the agent's tree — MUST pass;
(4) `smoke_no_regressions`. Fully mechanical, no judge.

## Implementation checks (107–108)

Existing check types suffice: `phpunit`, `smoke_no_regressions`,
`query_count`, `diff_size_limit`. The work is task authoring plus threshold
calibration (query budget, diff line budget) — no new evaluator code.

## ODB isolation (hard gate)

`WorktreeManager` gains an export-based prepare used for Phase 2 runs,
replacing `git worktree add`:

1. `git archive <base_ref> mock-project | tar -x` into the run dir — ONLY
   `mock-project/` is exported. `tasks/`, `runner/`, `docs/` never exist in
   the tree, killing the whole `../tasks` leakage class, not just ground
   truth.
2. `git init` + a single baseline commit in the run dir → ref `baseline`. No
   shared object database exists — `git show`/`git cat-file` have nothing to
   dig in. The plumbing leak documented in the parent spec is closed by
   construction, not by policy.
3. Existing checks keep working: `DiffSizeLimitCheck` runs
   `git diff --numstat <baseRef>` inside the tree; its check config points
   at the fresh repo's `baseline` ref, and the directory shape
   (`mock-project/` prefix) is preserved, so the check's file filter holds.
4. `composer install` runs after the baseline commit (the exported
   `.gitignore` keeps `vendor/` out of diffs).
5. **Fixture injection:** after the baseline commit, the runner copies
   task fixtures from `tasks/fixtures/<task-id>/` into the run dir:
   101 gets `PLAN.md`; 103 gets its PR patch applied and committed as
   `review-target`. Fixtures are runner-side inputs, never part of the
   exported ref.

Terminology: the task JSON's `export_ref` names the experiment-repo ref to
export (`scaffold_complete` for most tasks, `phase2-audit-target` for 102).
Inside the run dir, all diff-based checks use the fresh repo's `baseline`
ref. These are distinct settings; the parent spec's single `base_ref` is
split accordingly.

Ground truth (`tasks/ground-truth/*.json`) stays committed in the experiment
repo and is read runner-side by the evaluator only. Seeded defects live on
branch `phase2-audit-target`, squashed into one innocuous commit — sufficient
now, because no worktree ODB can reach the branch history.

Cost: export + init is seconds per run — negligible.

## Disposition and refusal detection

Already built in v2.1: `dispatch_disposition` field and reroute detection via
`model_id_reported`. Phase 2 adds the **refusal classifier** for artifact
tasks (101–105): findings/memo artifact absent or empty + refusal-phrase regex
→ `refused_in_band`; ambiguous cases escalate to a judge triage call.

Policy (from the parent spec, unchanged): record + re-dispatch up to 3 extra
attempts per cell toward N=5 clean observations; a cell that cannot get there
logs achieved N + interference rate and proceeds. `FindingsWriter` gains the
safeguard-interference section per (tier × category). This is a headline
result, not cleanup data: if Fable's interference is high on 101–103, the
measured dispatch rule becomes "route security review to Opus," regardless of
capability.

## Schema and plumbing changes

- `tasks/schema.json`: category enum += `plan_review`, `security_audit`,
  `code_review`, `architecture_decision`, `bugfix_no_repro`, `impl_hard`;
  `success_criteria` types += `findings_score`, `rubric_score`,
  `regression_red_green`; new optional fields `export_ref` (default
  `scaffold_complete`; see ODB section for the export-ref/diff-base split),
  `artifact_path`, threshold fields, findings cap.
- `experiment_config.json` (new campaign; rationale documented in
  `docs/limitations.md` per repo rule): `experiment_name:
  llm-dispatch-v2-phase2`, pinned `judge_model`, task ids 101–108.
- `ResultsRow`: nullable `metrics` object (P/R/F1, rubric scores, verdicts);
  defaults null for implementation tasks.
- `Aggregator`/`CellStats`/`FindingsWriter`: mean + bootstrap CI for
  recall/precision/rubric per cell; safeguard-interference section.
- Archive at the boundary as at the v2.0→v2.1 boundary: current
  `results.jsonl` → `results-v2.1-…jsonl` + git tag before a fresh campaign
  file is created.
- All evaluator logic is TDD'd; the matcher and red/green logic are pure
  unit-test subjects; judge calls are mocked in tests.

## Ground-truth discipline and authoring workflow

- **Every seeded defect ships with a detectability proof** (concrete
  trigger/exploit description or failing probe), authored BEFORE any run —
  otherwise "the tier missed it" and "it was unfindable" are
  indistinguishable.
- Natural mediocrity: the audit target must not smell like an experiment (no
  "task N" / "experiment" references; code looks like ordinary code).
- Workflow: defect seeding and plan/PR authoring are dispatched per task with
  explicit specs (Sonnet tier); the PM reviews every defect and verifies every
  detectability proof personally before the answer key freezes; thresholds are
  calibrated and frozen at the same time. Authoring is its own budget: roughly
  0.5–1M tokens, zero experiment runs.
- Freeze: task bank + ground truth + rubrics + thresholds are committed and
  tagged before the first dispatch — nothing is adjusted mid-campaign.

## Budget and execution order (NOT authorized by this spec)

1. **Build** (evaluator checks, WorktreeManager export, schema, plumbing):
   zero model runs, ordinary TDD development.
2. **Authoring** (defects, plan, PR, rubrics, proofs): ~0.5–1M tokens in
   dispatches.
3. **Pre-campaign gate checklist:** ODB isolation verified with a hostile
   probe (a subagent explicitly instructed to find the answer key — must
   fail); pin preflight for 4 tiers + judge; difficulty sanity on 107–108.
4. **Campaign:** 160 runs (8 × 4 × 5) + ~120 rubric calls + ~50–150 triage
   calls ≈ 3–5M tokens total, 2–5 days wall-clock. Started at a weekly
   rate-window reset, as a separate decision once build + authoring are done
   and the gate has passed.

## Known limitations (fold into `docs/limitations.md`)

- The judge model is weaker than the strongest judged tier (Opus judging
  Fable); mitigated by anchored rubrics and mechanical-first design. A
  constant judge bias survives relative tier comparison.
- Safeguard routing is non-reproducible (account- and time-dependent).
- N=5 with large expected effect sizes; bootstrap CIs as in v2.1.
- Single-shot means retry ability is not measured on review tasks —
  intentional, mirrors real dispatch usage.
