# Session Handoff — 2026-04-22

**Detta är en temporär handoff-fil.** Ny session: läs detta + `WORKLOG.md` + `DECISIONS.md`, fråga Anders vad som ska göras härnäst. När kontexten är inhämtad och nästa arbete pågår kan filen raderas eller arkiveras.

## Vad detta repo är

Kontrollerat experiment som mäter Claude modell-tiers (Haiku / Sonnet / Opus) som subagenter i en PM-dispatch-arkitektur. Mock-projektet är ett **support-ticket-system**. Mätning: cost-to-green och time-to-green per tier per task-typ.

## Var kontext-dokumenten ligger

**Allt ligger i detta repo:**
- `CLAUDE.md` — intern guidance för PM-sessioner här
- `WORKLOG.md` — löpande journal
- `DECISIONS.md` — arkitektur-/designbeslut
- `README.md` — publicerbar beskrivning
- `docs/superpowers/specs/2026-04-20-llm-dispatch-experiment-design.md` — fullständig spec
- `docs/superpowers/plans/2026-04-20-llm-dispatch-experiment-phase-1-infrastructure.md` — körd plan 1

Plan 2–5 skrivs och körs i detta repo. `/superpowers`-skills (brainstorming, writing-plans, executing-plans) är globala plugins och respekterar working directory — starta Claude Code i detta repos rot så hamnar nya plan-filer automatiskt på rätt path.

## Var vi står just nu

Plan 1 (infrastruktur) **klart**. 10 commits + tag `phase-1-complete`.

- Repot initialiserat
- MariaDB 10.11 via `docker-compose.yml` (named volume, port 3307)
- `runner/` med PHPUnit 11.5 installerat; `Config`-klassen är TDD:ad med 5 gröna tester
- `experiment_config.json` frusen (8 task-IDs, N=3, max_iter=3, timeout=1800s, policy=retry-only)
- `state.json` template (fylls av pre-flight i plan 3)
- Mappar klara för kommande faser: `mock-project/`, `tasks/`, `results/`, `docs/`, `worktrees/`

Verifiering:

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment/runner
./vendor/bin/phpunit   # → OK (5 tests)
cd ..
docker compose up -d   # → healthy on 3307
docker compose down
```

## Nästa steg

**Plan 2 — Mock-projektets base-kodbas** (~50 tasks, kan behöva delas).

Innehåll: Slim 4 MVC ticket-system i `mock-project/` med ~8 controllers, ~6 repositories, auth + RBAC (Admin / Agent / Requester), i18n (sv+en), migrations, Twig-templates, Alpine-komponenter. ~60 unit-tests + ~12 smoke-tests gröna. Slutpunkt: git-tag `scaffold_complete`.

Plan 2 skrivs först i bokningssidan-repot via `/superpowers:writing-plans`. Sen körs den här.

**Efter plan 2:** plan 3 (runner + evaluator), plan 4 (task-bank), plan 5 (analys + rapport).

## Kritiskt att komma ihåg

- **Två separata CLAUDE.md:** experimentrepots (denna mapp) refererar till experimentet och PM-rollen. Kommande `mock-project/CLAUDE.md` är generisk "typisk OSS-PHP-projekt"-nivå — **får inte** nämna experimentet. Subagenter som läser den ska inte veta att de testas.
- **`experiment_config.json` är frozen.** Ändra inte mid-experiment utan att dokumentera i `DECISIONS.md`.
- **Scaffolding räknas inte som experimentdata.** Experimentdata = endast körningar i plan 3 mot det då färdig-scaffoldade mock-projektet.
- **Vissa typer av filer är heliga:** `results/results.jsonl` är append-only. `worktrees/` committas aldrig (gitignored).

## Hur man startar en session i detta repo

```bash
cd /opt/homebrew/var/www/cc/llm-dispatch-experiment
# Starta Claude Code här (inte i bokningssidan-mappen)
```

Då blir detta repo "projektet" från Claude Codes perspektiv. Auto-memory, `/endsession`, CLAUDE.md-läsning — allt peker här.
