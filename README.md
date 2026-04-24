# LLM Dispatch Experiment

Controlled experiment measuring Claude model tiers (Haiku / Sonnet / Opus) as
subagents in a PM-dispatch architecture.

## What this measures

For each of 8 realistic coding task categories (CRUD, refactor, bugfix,
migration, i18n, RBAC-route, Alpine-frontend, N+1-fix), we measure
**cost-to-green** and **time-to-green** per model tier, including retry
iterations.

Policy A (retry-only) is the primary data collection. Policy B
(escalate-on-fail) is bootstrapped via Monte Carlo simulation from Policy A
data.

## Running the experiment

**New to Claude Code, macOS, or development?** Read
[`docs/running-the-experiment.md`](docs/running-the-experiment.md) — a
step-by-step guide from a blank Mac to a completed experiment.

**Already set up?** Minimal flow:

```bash
docker compose up -d
cd mock-project && php tools/migrate.php && php tools/seed_demo.php && cd ..
cd runner && composer install && cd ..
php runner/bin/cli state init
php runner/bin/cli state pin-models
php runner/bin/cli run-all
php runner/bin/cli report
```

The report lands at `docs/findings.md`.

## Stack

- **Mock project:** PHP 8.4 + Slim 4 + Twig + Alpine.js + MariaDB 10.11
- **Runner:** PHP 8.4 + PHPUnit
- **Orchestrator:** Claude Code subscription (not API)

## Documentation

- [`docs/running-the-experiment.md`](docs/running-the-experiment.md) — run guide
- [`docs/methodology.md`](docs/methodology.md) — experiment design and rationale
- [`docs/limitations.md`](docs/limitations.md) — caveats when interpreting results
- [`docs/findings.md`](docs/findings.md) — generated report (after running)

## License

MIT (see `LICENSE`).
