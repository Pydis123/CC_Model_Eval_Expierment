# LLM Dispatch Experiment

A controlled experiment measuring the cost and reliability of Claude's three
model tiers (Haiku / Sonnet / Opus) when used as subagents for realistic
coding tasks under project-manager dispatch orchestration.

72 dispatches were executed deterministically end-to-end, evaluated
mechanically (test pass / lint / query budget / file existence), and
analyzed with a seeded Monte Carlo bootstrap. The full pipeline,
data, analysis, and replication instructions are included in the repo.

## TL;DR

- **72 dispatches** — 8 coding task types × 3 model tiers × 3 replicates.
- **Pass rate:** Haiku 88%, Sonnet 96%, Opus 100%. Opus costs 2–4× the
  tokens and is 2–7× slower than Haiku.
- **Haiku is sufficient** for 5 of 8 task categories. Opus is required
  for 1 of 8 (cross-call-site reasoning, e.g. N+1 fixes). 2 of 8 are
  gray-zone.
- **Optimal strategy:** 3-tier escalation Haiku → Sonnet → Opus is
  cheapest in expectation among reliability-100% strategies — ~35%
  under all-Opus.
- **Biggest finding:** prompt specificity is a larger cost lever than
  tier choice. A 50-word tightening of a task brief can move work from
  "needs Opus" to "Haiku is fine."

Findings are bounded by N=3 per cell and a synthetic mock project. See
[`docs/limitations.md`](docs/limitations.md) for the complete caveats.

## Documentation map

| Path | Contents |
|---|---|
| [`docs/findings.md`](docs/findings.md) | Generated report — per-cell numbers, bootstrap CIs, Policy B simulation |
| [`docs/conclusions.md`](docs/conclusions.md) | Analytical layer — model→task fit, what's decisive vs. not |
| [`docs/applying-findings.md`](docs/applying-findings.md) | How to apply in real projects, with copy-pasteable CLAUDE.md snippets |
| [`docs/tier-picker.md`](docs/tier-picker.md) | One-page cheat sheet — decision tree + by-category table |
| [`docs/methodology.md`](docs/methodology.md) | Experiment design, evaluator, Policy A/B definitions |
| [`docs/limitations.md`](docs/limitations.md) | Caveats when interpreting results |
| [`docs/running-the-experiment.md`](docs/running-the-experiment.md) | Step-by-step replication guide (beginner-friendly) |
| [`docs/presentation/findings-sv.md`](docs/presentation/findings-sv.md) | Swedish summary — Facebook-friendly |
| [`docs/presentation/findings-en.md`](docs/presentation/findings-en.md) | English summary — Facebook-friendly |
| [`docs/presentation/findings-research.md`](docs/presentation/findings-research.md) | Research-style writeup |
| [`DECISIONS.md`](DECISIONS.md) | Locked-in design decisions and their rationale |

## Where to start

Pick the entry that matches your goal:

- **"Just give me the headline."** → [`docs/findings.md`](docs/findings.md)
- **"How do I apply this in my own projects?"** → [`docs/applying-findings.md`](docs/applying-findings.md) and [`docs/tier-picker.md`](docs/tier-picker.md)
- **"How was this designed?"** → [`docs/methodology.md`](docs/methodology.md)
- **"How do I reproduce it?"** → [`docs/running-the-experiment.md`](docs/running-the-experiment.md)
- **"What can't I conclude from this?"** → [`docs/limitations.md`](docs/limitations.md)
- **"Forecast cost for my own workload."** → `php runner/bin/cost-calculator.php --help`
- **"Forwardable summary."** → [`docs/presentation/findings-en.md`](docs/presentation/findings-en.md) (or `-sv.md`)
- **"Research-style writeup."** → [`docs/presentation/findings-research.md`](docs/presentation/findings-research.md)

## Repo layout

```
.
├── docs/                              Methodology, findings, analysis, presentations
│   ├── findings.md                    Generated report
│   ├── conclusions.md                 Analytical layer
│   ├── applying-findings.md           Practical-use guide
│   ├── tier-picker.md                 Cheat sheet
│   ├── methodology.md                 Design + Policy B simulation
│   ├── limitations.md                 Caveats
│   ├── running-the-experiment.md      Replication guide
│   └── presentation/                  Forwardable summaries (sv, en, research)
├── tasks/                             Frozen task bank — JSON specs + markdown prompts
├── mock-project/                      PHP/Slim/Twig/Alpine/MariaDB target codebase
├── runner/                            Orchestrator (PHP 8.4 + PHPUnit)
│   ├── bin/cli                        Main CLI: state init, run-all, report
│   └── bin/cost-calculator.php        Cost forecaster for your workload
├── docker/                            MariaDB init scripts
├── results/results.jsonl              Raw data (one row per dispatch — append-only)
├── state.json                         Experiment state (frozen after completion)
├── experiment_config.json             Frozen experiment parameters
├── DECISIONS.md                       Design-decision log
└── LICENSE                            MIT
```

## Quick reproduction

For a complete walk-through aimed at someone new to the tools, see
[`docs/running-the-experiment.md`](docs/running-the-experiment.md). The
condensed flow:

```bash
docker compose up -d
cd mock-project && php tools/migrate.php && php tools/seed_demo.php && cd ..
cd runner && composer install && cd ..
php runner/bin/cli state init
php runner/bin/cli state pin-models \
  --haiku=claude-haiku-4-5-20251001 \
  --sonnet=claude-sonnet-4-6 \
  --opus=claude-opus-4-7
php runner/bin/cli run-all
php runner/bin/cli report
```

The report lands at [`docs/findings.md`](docs/findings.md). Expect 10–30
hours of wall-clock time depending on Anthropic rate-limit windows.

## Reproducibility

The pipeline is deterministic end-to-end:

- `plan_seed=42` drives both dispatch order (seeded Fisher–Yates) and
  bootstrap sampling (1000 iterations).
- PHP dependencies are pinned via `composer.lock`.
- The mock project is frozen at a tagged git ref (`scaffold_complete`).
- The task bank is frozen — JSON specs not modified after experiment start.
- Pinned model IDs are recorded in `state.json` and verified per dispatch.
- `docs/findings.md` is regenerated by `runner/bin/cli report`. Diff your
  regenerated copy against the committed file to verify byte-for-byte.

Anything non-reproducible (e.g. server-side model retuning under an alias)
is documented in [`docs/limitations.md`](docs/limitations.md).

## Stack

- **Mock project:** PHP 8.4 + Slim 4 + Twig + Alpine.js + MariaDB 10.11
- **Runner:** PHP 8.4 + PHPUnit 11
- **Orchestrator:** Claude Code subscription (not Anthropic API), invoked
  via `claude -p ...` subprocess per dispatch
- **Container:** Docker Compose (MariaDB), no other services

## What this does NOT measure

Important to set expectations explicitly:

- Code quality, maintainability, idiomatic style, security — the
  evaluator is mechanical (tests pass / lint / query count / file
  existence)
- Behavior on adversarial prompts or distractor instructions
- Multi-session work, cross-repo coordination, or open-ended exploration
- Sensitivity to prompt phrasing — each task has one frozen prompt
- Real-codebase generalization — the mock project is synthetic with
  planted anti-patterns at known density

See [`docs/limitations.md`](docs/limitations.md) for the full discussion
and how each limitation affects which conclusions are safe to draw.

## License

MIT — see [`LICENSE`](LICENSE).
