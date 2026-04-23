# Session Handoff — 2026-04-23 (mitt i Plan 6)

**Temporär handoff. Ny session: läs detta + `WORKLOG.md` + `DECISIONS.md`, fortsätt med Task 15 nedan.**

## Var vi står

**Plan 5 klar.** **Plan 6 är påbörjad — Tasks 1-14 av 21 klara.** Pausat mellan Task 14 och Task 15.

- **282 tester gröna** (från 223 efter plan 5)
- PHPStan level 6 rent
- Alla commits atomiska, inget halvfärdigt i arbetsträdet
- Git är rent (inga staged/unstaged ändringar)

## Nästa steg — Task 15 i Plan 6

Planfilen: `docs/superpowers/plans/2026-04-23-experiment-execution.md`

**Task 15: `RunNextCommand`** — CLI-kommando som kör EN run end-to-end. Claimar från state.json, preparerar worktree, kör 1-3 iterationer via `RunCoordinator`, appendar `results.jsonl`, uppdaterar state, städar worktree.

Sonnet för task 15 (komplexitet: multi-class wiring + row-building). Haiku/Sonnet-mix för tasks 16-21 enligt plan.

### Återstående tasks efter 15

- **Task 16:** `RunAllCommand` — looping + 5-error-abort + crash-dump. Sonnet.
- **Task 17:** `StateResetStaleCommand` — parse duration-flagga, clear stale claimedAt. Haiku.
- **Task 18:** Wire 3 commands i `runner/bin/cli` + `.gitignore`-tillägg (`worktrees/failed/`, `results/runner.log`, `results/runner-crash-*.json`). Sonnet.
- **Task 19:** Opt-in real-claude smoke test. Haiku.
- **Task 20:** `docs/limitations.md`-tillägg om `~/.claude/CLAUDE.md`-leakage. Skriv direkt (inte subagent).
- **Task 21:** PHPStan + full-suite verifiering (ingen ny kod).

## Viktiga kontext-beslut från brainstorming (låsta)

- **Fix 1:** Worktree vid `/tmp/llm-disp-run-<id>/` med experiment-root `CLAUDE.md` `rm`:ad. Accepterar `~/.claude/CLAUDE.md`-leakage → dokumenteras i limitations.md (Task 20).
- **R1:** Iteration 2-3 får original-prompt + komprimerad `FailedChecksSummarizer`-output appended. Inte worktree-reset.
- **Seriell dispatch.** Parallell skulle introducera CPU-kontention-brus i wall-clock.
- **`stream-json --verbose` inte `json`.** Ger strukturerad `rate_limit_event` med `resetsAt` → exakt sleep-tid.
- **`ClaudeCli` interface + PHPUnit mocks.** Återanvänder `ProcessExecutor`-mönster. En opt-in smoke-test mot riktig CLI finns planerad (Task 19).
- **5 consecutive unexpected errors → abort + crash-dump.** Räknas inte: rate-limit (sleep och retry), normal passed/failed (registered). Räknas: is_error, malformed JSON, timeout, worktree-failure, evaluator-crash.

## Avvikelse från planen att komma ihåg

**Task 8 introducerade `EvaluatorInterface`** — `Evaluator` var `final` och plan-test-stubbens anonymous-class-extend hade failat. Subagenten extraherade interface, `Evaluator` implements det, `RunCoordinator` type-hintar interfacet. Legitim fix, ingen scope-violation. Betyder: om du behöver mocka Evaluator i framtida test, implementera `EvaluatorInterface`.

## Commits plan 6 så här långt

Första: `1e8298e` (ClaudeCliResponse + RateLimitInfo)
Senaste: `4f667e8` (ProgressLogger)

14 tasks × i genomsnitt 1.3 commits/task (några batchade 3 tasks i en dispatch med separata commits).

## Verifiering av state vid återupptagande

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
git status                                   # → clean
git log --oneline -3                         # → 4f667e8 ProgressLogger
cd runner && vendor/bin/phpunit 2>&1 | tail -3
# → Tests: 282, Assertions: 810, PHPUnit Deprecations: 1
```

`PHPUnit Deprecations: 1` är pre-existing (XML-schema från en äldre PHPUnit-version). Ignoreras.

## Kör inte om

- **Rör inte committed Plan 6-kod i `runner/src/Dispatch/` eller `runner/src/Execution/`** om du inte får explicit instruktion. Task 15+ bygger ovanpå dessa klasser.
- **Rör inte `tasks/`-mappen** — frusen task-bank från Plan 4.
- **Rör inte `experiment_config.json`, `state.json`**, `results/results.jsonl`.

## Hur man återupptar

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
# Starta Claude Code här
```

Anders triggar med "fortsätt med Task 15" eller liknande.
