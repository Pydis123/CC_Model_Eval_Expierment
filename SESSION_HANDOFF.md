# Session Handoff — 2026-04-22 (efter Plan 2a + 2b)

**Temporär handoff. Ny session: läs detta + `WORKLOG.md` + `DECISIONS.md`, fråga Anders vad som ska göras härnäst.**

## Var vi står

Plan 1, Plan 2a och **Plan 2b klart**. Mock-projektet är fullt scaffoldat — `scaffold_complete`-tag satt på `5f43ac0`.

- **100 tester gröna** (Unit + Integration + Smoke, 203 assertions)
- PHPStan level 6 rent
- Fullt körbar end-to-end: `docker compose up -d` → `php tools/migrate.php` → `php tools/seed_demo.php` → `php -S localhost:8080 -t public`
- Svenska default locale; byte via `GET /locale/{sv|en}`; lazy i18n-resolution
- Alpine.js med 5 komponenter: flash, mobile-nav, user-menu, status-filter tabs, confirm-modal
- Tailwind via CDN (Play script)
- 130 translation-rader seedade (65 sv + 65 en)

### Naturlig mediokerhet bevarad

- N+1 i `TicketController::index` — kvar (guard-test bekräftar >20 queries)
- Inline state-machine i `TicketController::changeStatus` — kvar
- Planterad intermittent test i `RecentActivityService` — kvar
- Ingen `sla_deadline`, ingen `tags`, ingen batch-close-route, ingen Alpine-composer
- 5 specifika i18n-rader som ska saknas bestäms **i plan 4** när task 1-prompten skrivs

### Integritet

- Ingen experiment-leakage i mock-projektet (`grep -iErn "experiment|dispatch|evaluator|mediocrity|subagent|PM role" mock-project/` → tomt)
- `mock-project/CLAUDE.md` är generisk
- Alla scaffold-commits har neutrala meddelanden

## Nästa steg — Plan 3

**Runner + evaluator + state_manager + model_pin_check.** Dessa är PHP-utilities i repo-rooten under `runner/` (redan börjat i plan 1), inte under `mock-project/`. Innehåll:

- `runner/src/Evaluator.php` — kör `phpunit`, smoke-tests, query-count mot ett run
- `runner/src/StateManager.php` — läser/skriver `state.json`; fyller `pinned_models`
- `runner/src/ModelPinCheck.php` — pre-flight detect + pre-dispatch guard mot model-drift
- Integration med Claude Code `Agent`-tool för dispatch (dokumenterad men ej körbar från runner — subscriptionen sköter dispatch via Claude Code-sessionen)
- Tester för allt

Brainstorming + spec + plan skrivs i detta repo (samma flöde som 2a/2b).

## Efter Plan 3

- **Plan 4:** task-bank — 8 task-prompter + success-criteria JSON. Här bestäms vilka 5 i18n-rader som saknas för task 1.
- **Plan 5:** analys + rapport-template (markdown-generatorer, bootstrap-simulering för Policy B).

## Verifiering av nuvarande state

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
docker compose ps                      # → llm-dispatch-mariadb Up (healthy)
cd mock-project
./vendor/bin/phpunit                   # → OK (100 tests, 203 assertions)
./vendor/bin/phpstan analyse           # → [OK] No errors
git describe                           # → scaffold_complete eller scaffold_complete-N-gXXX
```

## Kör inte om

- **Rör inte mock-project/ för scaffolding-ändamål.** Eventuella fixes görs som egna commits efter `scaffold_complete`-taggen, INTE i scaffolden själv. Experiment-tasks ska börja från taggen.
- **Rör inte `docker-compose.yml`, `docker/init/*.sql`, eller `experiment_config.json`** — frusna.
- **Rör inte `.env.example`** — port 3307 är medvetet val.

## Hur man återupptar

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
# Starta Claude Code här
```

Anders triggar med fråga om vi kör Plan 3 eller något annat.
