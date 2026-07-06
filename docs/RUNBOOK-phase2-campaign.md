# Phase 2 campaign — pre-go runbook

**Status:** NOT started. Compiled 2026-07-06 for Anders's go. Nothing here
runs until explicitly approved. The task bank is frozen (`phase2-taskbank-frozen`).

## Locked decisions

- **N = 5 replicates.** 8 tasks (101–108) × 4 tiers × 5 = **160 runs.**
- **Include Fable** as the 4th tier.
- **Judge model:** `claude-opus-4-8` (rubric + triage).
- **Billing/timing:** two batches appending to one `results.jsonl`, same 160
  runs total:
  - **Batch 1** = haiku + sonnet + opus (120 runs), usage-credits **OFF**,
    subscription only. May start anytime.
  - **Batch 2** = fable only (40 runs), usage-credits **ON**, after the
    July 7/8 cutover. Metered ~$30–50, not ration.

Legend: **[me]** = runner/PM action (zero or model-dispatch as noted).
**[you]** = manual action on claude.ai only you can do.

---

## Phase 0 — Campaign boundary (ZERO model dispatch)

- [ ] **[me]** Archive v2.1 data at the boundary (like v2.0→v2.1):
      `git mv results/results.jsonl results/results-v2.1-<date>.jsonl`,
      `git mv docs/findings.md docs/archive/findings-v2.1.md`, git-tag
      `v2.1-data`. Start a fresh empty `results/results.jsonl`.
- [ ] **[me]** Edit `experiment_config.json` (documented boundary, not a
      mid-experiment mutation — v2.1 is complete):
      - `experiment_name` → `llm-dispatch-v2-phase2`
      - `task_ids` → `101-plan-review … 108-query-budget-perf` (the 8 new)
      - `judge_model` → `claude-opus-4-8`
      - `n_replicates` → 5 (unchanged)
      - `tiers` → `["haiku","sonnet","opus"]` (Batch 1 set; edited again later)
      - Record the boundary rationale in `docs/limitations.md`.
- [ ] **[me]** `state pin-models` with the four current ids — confirm exact
      ids at pin time, prefer dated snapshots if published. v2.1 used
      `claude-haiku-4-5-20251001 / claude-sonnet-5 / claude-opus-4-8 /
      claude-fable-5`. (Pin all four now even though Batch 1 runs three.)
- [ ] **[me]** `php runner/bin/cli validate-tasks` → must be all-OK (answer
      keys, rubrics, prompts, fixtures resolve).
- [ ] **[me]** Confirm MariaDB up on :3307 (`docker compose up` if not).
- [ ] Already proven, no action: ODB isolation (git-ODB vector closed),
      contamination detector wired, ground-truth audit 29/29.

## Phase 1 — Difficulty/harness smoke (SMALL model dispatch — first spend)

Before committing 160 runs, dry-run **one run per task** to catch
config/threshold/wiring errors cheaply (this is where the 107/108
query-count and diff thresholds get their live confirmation, and where the
export/fixture/review-target mechanics get exercised end to end).

- [ ] **[me]** Run 8 single dispatches (one tier, e.g. haiku/sonnet as
      appropriate) across 101–108; inspect: findings artifact written &
      scored, rubric memo scored, 106 red/green fires, 107/108 checks behave,
      no `contaminated` false-positives, export isolation holds. Cost: ~8
      runs. **This is the first model spend and needs your go.**
- [ ] **[me]** Fix any threshold/wiring issue found; re-freeze if a
      ground-truth/threshold changes.

## Phase 2 — Batch 1: subscription tiers (MODEL dispatch, ration)

- [ ] **[you]** Confirm usage-credits are **OFF** on claude.ai (default). If
      the weekly limit is hit mid-batch it will throttle and resume at reset —
      it CANNOT draw credits while off.
- [ ] **[me]** `state init --force` (tiers = haiku,sonnet,opus) → run-all,
      detached, per the pause/resume runbook. 120 runs, est. 2–4 days across
      rate windows.
- [ ] **[me]** On completion: sanity check (each of the 3 tiers has clean
      N=5 on all 8 tasks; interference/contamination counts sane).

## Phase 3 — Handoff: enable Fable credits

- [ ] **[you]** After the July 7/8 cutover, on **claude.ai → Settings →
      Usage** (web, not mobile): enable usage-credits, deposit a small fixed
      balance (e.g. $50), set a **spend limit**. Tell me when done.

## Phase 4 — Batch 2: Fable only (MODEL dispatch, metered $)

- [ ] **[me]** Edit `experiment_config.json` `tiers` → `["fable"]`.
- [ ] **[me]** `state init --force` → run-all, detached. 40 Fable runs append
      to the same `results.jsonl`. Only Fable is dispatched, so only Fable can
      draw credits. Expect safeguard interference on 101/102/103 (a finding).
- [ ] **[me]** On completion: Fable N=5 achieved (or achieved-N + interference
      rate recorded per the disposition policy).

## Phase 5 — Handoff: disable Fable credits

- [ ] **[you]** claude.ai → Settings → Usage → disable usage-credits.
      (Spend limit + fixed balance already capped it regardless.)

## Phase 6 — Report + Phase 3 rewrite (ZERO model dispatch)

- [ ] **[me]** Edit `experiment_config.json` `tiers` → all four (so the
      aggregator validates all 160 cells).
- [ ] **[me]** `report` (findings.md with recall/precision/rubric + CIs +
      safeguard-interference section) and `report-delta` as applicable.
- [ ] **[me]** Rewrite the Opus/Fable rows in `~/.claude/CLAUDE.md` from
      MEASURED data (Phase 3 of the recalibration) — versioned against the
      pinned ids, dated.
- [ ] **[me]** Archive the campaign; tag the dataset.

---

## Cost / time summary

- Batch 1 (120 subscription runs): draws from the weekly ration — this is the
  "% of your pott" part. Est. 2–4 days.
- Batch 2 (40 Fable runs): ~$30–50 metered credits, not ration. Est. 1–2 days.
- Judge calls (~120 rubric + ~50–150 triage) run on Opus → subscription, not
  credits.
- Total campaign ≈ 3–5M tokens.

## Abort / pause

`runner/bin/pause` (graceful, 30-min heads-up) / `pause-now` (hard,
data-safe) / `resume`. A network drop mid-run consumes the observation — see
the pause/resume runbook.

## Open items to confirm at go-time

1. Exact pinned model ids (dated snapshots if published) — check at pin time.
2. The July 7/8 cutover exact time — gate Batch 2 after it.
3. Your go for the Phase 1 smoke (first model spend, ~8 runs).
