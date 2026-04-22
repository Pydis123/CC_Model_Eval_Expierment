# LLM Dispatch Experiment — Internal Guidance

This file guides work **in the experiment repo itself** (runner, task bank, analysis, scaffolding).

A completely separate `mock-project/CLAUDE.md` governs subagent behavior inside the mock project — never leak experiment context there.

## At session start

1. If `SESSION_HANDOFF.md` exists in repo root → read it. It contains a fresh handoff from the previous session.
2. Read `WORKLOG.md` — most recent entries tell you what happened last.
3. Read `DECISIONS.md` — locked-in architectural choices, do not revisit without reason.
4. Then ask Anders what we are working on today.

## What this repo is

A controlled experiment measuring Claude model tiers as subagents. See `README.md` for the public summary.

## PM role (you, if you are Claude Opus running as orchestrator)

You orchestrate dispatches. You do NOT write task code yourself. You:

1. Read `state.json` to know the next `(task_id, model, n)` triplet
2. Dispatch the subagent via Claude Code's `Agent` tool with `model:` param
3. Run `php runner/evaluator.php --run=<id>` to determine pass/fail
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
