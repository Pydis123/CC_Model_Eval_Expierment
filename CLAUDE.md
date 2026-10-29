# LLM Dispatch Experiment — Internal Guidance

This file guides work **in the experiment repo itself** (runner, task bank, analysis, scaffolding).

A completely separate `mock-project/CLAUDE.md` governs subagent behavior inside the mock project — never leak experiment context there.

## At session start

1. Read `DECISIONS.md` — locked-in architectural choices, do not revisit without reason.
2. Read `docs/methodology.md` if you need to understand experiment design.
3. Then ask the user what we are working on today.

If a personal `WORKLOG.md` exists at repo root (gitignored — kept locally
by the maintainer), read its most recent entries for additional context
on what happened last. The file is optional and may be absent.

## What this repo is

A controlled experiment measuring Claude model tiers as subagents. See `README.md` for the public summary.

## PM role (you, if you are Claude Opus running as orchestrator)

You orchestrate dispatches. You do NOT write task code yourself. You:

1. Read `state.json` to know the next `(task_id, model, n)` triplet
2. Dispatch the subagent via Claude Code's `Agent` tool with `model:` param,
   OR delegate to the automated runner via `php runner/bin/cli run-all`
3. Use the runner's evaluator to determine pass/fail
4. Log to `results/results.jsonl`
5. Update `state.json`
6. Report to user and (when appropriate) request `/clear`

Do not do the subagent's work yourself. That invalidates the experiment.

## Conventions

- PHP 8.4 with `declare(strict_types=1)` in every file
- PSR-12 formatting, PascalCase classes, camelCase methods
- English only (in this repo — the experiment generalizes beyond Swedish contexts)
- TDD: write the failing test first, then implement
- Frequent small commits

## Layout

```
experiment_config.json   # constants (N, max_iter, timeouts, plan_seed, pinned_models)
state.json               # live state (mutable)
mock-project/            # ticket-system (subagent target)
tasks/                   # frozen task definitions
runner/                  # PHP utilities (evaluator, state-manager, model-pin-check)
results/                 # JSONL logs (append-only)
worktrees/               # git worktrees per run (runtime, gitignored)
docs/                    # methodology, findings, limitations
```

## Critical rules

- **Never edit `results/results.jsonl` by hand.** It's append-only and experiment data.
- **Never commit `worktrees/`** — gitignored.
- **Do not modify `experiment_config.json` or `state.json` mid-experiment** without documenting why in `docs/limitations.md`.
- **Mock-project's `CLAUDE.md` is off-limits from a "help the subagent" perspective** — never add hints there that bias results.

## Keep the public docs in sync with the results

Whenever a run changes a headline — a new calibration round, a new lineup, a
pass-rate or safeguard result that moves a conclusion — update the public-facing
docs in the **same session**, not "later". Treat this as part of finishing the
run, the way tests are part of finishing code. The docs that must not go stale:

- `README.md` — the repo front page. Keep the status section, the
  **cross-version change table** (one row per round: date · lineup · scope ·
  headline), the model-tier IDs, the results tables, the pinned-model IDs in
  the reproduce block, and the documentation-map currency column all current.
- `docs/presentation/findings-sv.md`, `findings-en.md`, `findings-research.md`
  — the forwardable summaries (sv/en popular + the formal paper). Update the
  TL;DR, the per-round change table, and the copyable "configure CLAUDE.md"
  block. For the research paper, add a dated addendum rather than rewriting the
  numbered sections, and note which numbered claims it supersedes.
- The user's global model-selection guidance when the dispatch advice itself
  changes (ask before editing files outside this repo).

Rule of thumb: if a new finding contradicts a sentence already written in any
of the above, the fix is not optional and is not a tracker item — correct it
now, and frame the superseded round as historical rather than deleting it.
Per-round detail lives in `docs/findings-*.md`; the public docs summarise and
must point at the current one.
