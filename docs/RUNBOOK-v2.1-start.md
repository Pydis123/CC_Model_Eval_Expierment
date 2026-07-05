# Runbook: starting the v2.1 re-run (isolated harness)

v2.1 re-runs all 160 observations with the fixed harness. **Nothing in this
runbook has been executed until the checklist below is ticked in WORKLOG.**

## Why a full re-run

v2.0 (160 runs, 2026-07-03 → 2026-07-05) carries two harness confounds:

1. **Diff-trap**: worktree prune left ~2,330 lines of unstaged infra
   deletions that `diff_size_limit` counted, forcing a discover-and-repair
   iteration in every 001/002/004 run (inflated tokens/iterations; three
   001 runs failed on repair, not on the feature).
2. **Context leakage**: dispatched `claude -p` sessions loaded the
   operator's user-level CLAUDE.md (PM role, tier tables, skills), so runs
   measured "model + operator harness", not the model as implementer.

v2.1 fixes: diff check filtered to `mock-project/`, `--setting-sources
project`, sanitized child env (no `CLAUDE_*`/`ANTHROPIC_*`, PATH without
the claude binary dir, `DISABLE_AUTOUPDATER=1`), auto-requeue of
`claude_cli_is_error` rows capped at 3 per run_id, aggregator excludes
unreplaced error rows.

## Start procedure

1. **Freeze v2.0 data**
   - `git add`-anything pending, commit, tag `v2.0-data`.
   - `mv results/results.jsonl results/results-v2.0-harness-confound-2026-07.jsonl`
   - Archive alongside: `run-all-v2.log`, `net-keepalive.log`, crash dump,
     `state.json.bak-*` → `results/archive-v2.0/` (git-tracked or listed in
     WORKLOG). Update `docs/limitations.md` if not already done.
2. **Config**: set `experiment_name` to `llm-dispatch-v2.1-isolated` in
   `experiment_config.json` (version boundary, documented — not a
   mid-experiment mutation).
3. **State**: `php runner/bin/cli state init --force` then
   `php runner/bin/cli state pin-models`. Probe for dated snapshot ids for
   sonnet/opus/fable first; pin dated ids if published.
4. **Pre-flight**: `./runner/bin/preflight --clean` — all green/warn-ok.
   On hotspot uplink, start `net-keepalive` first.
5. **Smoke run**: `php runner/bin/cli run-all --max-runs=1` (foreground or
   watched). Inspect the produced row before continuing:
   - `result_text` is implementer-style English — NO PM narrative, no
     "dispatched to Haiku", no superpowers/plan-document references.
   - `diff_size_limit.per_file` contains only `mock-project/` paths and
     `excluded_non_mock_project_files` ≈ 18.
   - `dispatch_disposition` = completed; `claude_cli_version` as expected.
6. **Release the fleet**: `./runner/bin/resume` (detached, logs to
   `results/run-all-v2.log`), arm liveness monitor, note start in WORKLOG.
7. **After completion**: `report`, then `report-delta` twice —
   v1 → v2.1 (generational) and v2.0 → v2.1 (operator-harness effect,
   same models/tasks, only context differs).

## Expected profile

~160 × 5–9 min ≈ 17–20 h serial. Quota (Max x20) allows ~40 v2.0-sized
runs per 5 h window; v2.1 runs are cheaper, so serial pace should stay
under the cap. Parallel execution was evaluated and rejected for v2.1
(quota-capped gain, unlocked-state races, breaker fragmentation) — see
WORKLOG 2026-07-05.
