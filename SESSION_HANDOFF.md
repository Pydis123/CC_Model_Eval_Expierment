# Session Handoff — 2026-04-22 (efter Plan 2a)

**Temporär handoff. Ny session: läs detta + `WORKLOG.md` + `DECISIONS.md`, fråga Anders vad som ska göras härnäst.**

## Var vi står

Plan 1 och Plan 2a **klart**. Backend-scaffolden för `mock-project/` är på plats.

- 74 tester gröna (Unit + Integration + Smoke, 142 assertions)
- PHPStan level 6 rent
- Docker MariaDB (port 3307) + init-script för `ticket_system_test`-DB
- `tools/migrate.php` + `tools/seed_demo.php` funktionella; main-DB seedad (50 tickets, 99 comments)
- Natural-mediokert scaffold:
  - N+1 i `TicketController::index` (guard-test passes)
  - Inline state-machine i `TicketController::changeStatus`
  - Planterad intermittent test i `RecentActivityService`
  - Ingen `sla_deadline`, ingen `tags`, ingen batch-close, ingen Alpine-composer
- `mock-project/CLAUDE.md` och all scaffolded-kod är skrubbad från experiment-referenser

## Nästa steg — Plan 2b

**Frontend + i18n för mock-projektet.** Innehåll:

- Fulla Twig-templates (layout, partials)
- Tailwind via CDN
- Alpine.js data-attribute-komponenter (minimalt — composer-komponent är task 8:s jobb)
- i18n-infrastruktur: `t()`, `tp()` Twig-functions, DB-baserad `i18n_strings`-tabell
- ~150 translation-rader (sv + en)
- ~10-15 UI-fokuserade tester + ~6 smoke-tester för renderat innehåll

Brainstorming + spec + plan skrivs i detta repo (samma flöde som Plan 2a).

Slutpunkt Plan 2b: git-tag `scaffold_complete`.

## Verifiering av nuvarande state

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
docker compose ps                      # → llm-dispatch-mariadb Up (healthy)
cd mock-project
./vendor/bin/phpunit                   # → OK (74 tests, 142 assertions)
./vendor/bin/phpstan analyse           # → [OK] No errors
php tools/seed_demo.php                # → "Users table already populated, skipping"
```

## Efter Plan 2b

- Plan 3: runner + evaluator + state_manager + model_pin_check
- Plan 4: task-bank (8 task-prompts + success criteria)
- Plan 5: analys + rapport-template

## Referenser

- Plan 2a-spec: `docs/superpowers/specs/2026-04-22-mock-project-backend-scaffold-design.md`
- Plan 2a: `docs/superpowers/plans/2026-04-22-mock-project-backend-scaffold.md`
- Master-spec: `docs/superpowers/specs/2026-04-20-llm-dispatch-experiment-design.md`
- WORKLOG: `WORKLOG.md` (detaljerad Plan 2a-sammanfattning + avvikelser)
- DECISIONS: `DECISIONS.md`

## Kör inte om

- **Ändra inte `mock-project/CLAUDE.md`** — den är explicit generisk för att inte biasa subagenter i experimentet
- **Ändra inte `docker-compose.yml` port 3307** — det är det frusna valet i `experiment_config.json`
- **Ändra inte `.env.example`** — port ska vara 3307 (docker-forward), inte host-MariaDB:s 3306

## Hur man återupptar

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
# Starta Claude Code här
```

Anders triggar med fråga om vi kör Plan 2b eller något annat.
