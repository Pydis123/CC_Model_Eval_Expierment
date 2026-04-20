# LLM Dispatch Experiment

Controlled experiment measuring Claude model tiers (Haiku / Sonnet / Opus) as subagents in a PM-dispatch architecture.

## What this measures

For each of 8 realistic coding task categories (CRUD, refactor, bugfix, migration, i18n, RBAC-route, Alpine-frontend, N+1-fix), we measure **cost-to-green** and **time-to-green** per model tier, including retry iterations.

Policy A (retry-only) is the primary data collection. Policy B (escalate-on-fail) is bootstrapped via Monte Carlo simulation from Policy A data.

## Design

Full design spec lives externally at the author's primary project repository. A methodology summary is in `docs/methodology.md` (added in a later phase).

## Stack

- **Mock project:** PHP 8.4 + Slim 4 + Twig + Alpine.js + MariaDB 10.11
- **Runner:** PHP 8.4 + PHPUnit
- **Orchestrator:** Claude Code subscription (not API)

## Running

```bash
# Start the database
docker compose up -d

# Run experiment runner tests
cd runner && composer install && ./vendor/bin/phpunit
```

Full run instructions are added once the runner and task bank are implemented (phases 3 and 4).

## Status

Phase 1: Infrastructure scaffold (this commit).

## License

MIT (pending, see LICENSE file added in later phase).
