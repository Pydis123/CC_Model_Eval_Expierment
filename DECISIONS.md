# Arkitektur- och designbeslut

## 2026-04-20 — Experiment som eget repo (ej del av bokningssidan)

**Beslut:** LLM dispatch-experimentet bor i `/opt/homebrew/var/www/cc/llm-dispatch-experiment/`, separat från bokningssidan.

**Motivering:**
- Mock-projektet ska vara ny kodbas — inte bokningssidan-kontamination
- Olika livscykel för produkt vs experiment (CLAUDE.md, WORKLOG, tests)
- Runner är återanvändbar infrastruktur för framtida experiment
- Egen `.claude/`-konfig möjlig utan arv från bokningssidan
- Publicerbart som självständig GitHub-repo

## 2026-04-20 — MariaDB (ej SQLite)

**Beslut:** Mock-projektet kör MariaDB 10.11 via Docker Compose.

**Motivering:** SQLite har funktionsskillnader som biasar resultat:
- `NOW()` / `DATE_SUB(..., INTERVAL ...)` mot `datetime('now', ...)`
- `ON DUPLICATE KEY UPDATE` mot `ON CONFLICT ... DO UPDATE`
- `FULLTEXT INDEX` + `MATCH() AGAINST()` mot `FTS5`
- VARCHAR-längd ignoreras i SQLite
- FK default off i SQLite

Docker Compose är industristandard i PHP-ekosystemet. Peer-review-barriären är låg.

## 2026-04-20 — Support-ticket-system som mock-domän

**Beslut:** Mock-projektet är ett ticket-system (tickets, customers, companies, kategorier, state-machine, SLA, kommentarer, tags).

**Motivering:** Rik på naturliga task-typer för alla 8 kategorier (CRUD, state machine, migration, refactor, bugfix, RBAC, frontend, i18n). Mindre tränings-exponerat än blogg/CMS. Annorlunda vokabulär från bokningssidan så Claude inte auto-completar från träning.

Alternativ övervägda: invoice-system (för regel-tungt), inventory (torrare affärslogik), CMS (för tränings-exponerat).

## 2026-04-20 — React/andra frontend-stacks parkeras till v2

**Motivering:** Anders kör Twig+Alpine, inte React. Findings-actionability för dagligt arbete är viktigaste drivern. Stack-agnostisk infrastruktur tillåter v2-utbyggnad senare.

## 2026-04-20 — 8 task-kategorier, 1 canonical task per

**Beslut:** v1 har 8 kategorier: i18n, CRUD, N+1-fix, migration+backfill, state-service-refactor, bugfix-med-rot-orsak, RBAC-route, Alpine-frontend-komponent. En canonical task per. Totalt 72 primärkörningar (8 × 3 tiers × N=3).

**Motivering:** Balanserad scope för v1. Storleksberoende (liten+stor per kategori) → v2. Webhook + cron → v2.

## 2026-04-20 — Claude Code-subscription, inte API

**Beslut:** Experimentet körs via Claude Code-subscription. PM är Opus i en Claude Code-session; subagenter dispatcha's via `Agent`-toolet med `model`-enum.

**Motivering:** Sparar API-pengar (Anders har subscription). Trade-off: förlorar intra-Opus-version-jämförelse (4.6 vs 4.7) eftersom Agent-toolet bara tar tier-enum (haiku/sonnet/opus). Acceptabel förlust eftersom CLAUDE.md-regel är på tier-nivå, inte version-nivå.

## 2026-04-20 — Modell-låsning via detect-and-halt

**Beslut:** Eftersom Claude Code saknar version-pinning implementeras låsning via: pre-flight → logga `model_id` i `pinned_models`. Pre-dispatch-check varje körning. Post-dispatch-logging av `model_id` i JSONL för drift-detektion.

**Motivering:** Det starkaste skydd som är praktiskt möjligt utan API-direktanrop. Accepterar att Anthropic kan uppdatera tier-default mid-experiment — då pausas experimentet och Anders beslutar.

## 2026-04-20 — Policy A primär + Policy B simulerad

**Beslut:** Policy A (retry-only, max 3 iter) är datainsamling (72 körningar). Policy B (escalate-on-fail) bootstrappas från A-datan via Monte Carlo. Valfri valideringsomgång på upp till 8 körningar om simulering överraskar.

**Motivering:** Att köra båda policies separat skulle dubbla budgeten. Simulering ger directional signal till noll extra token-kostnad. Metodologisk begränsning (simulering fångar inte PM-feedback-effekt vid eskalation) dokumenteras explicit i slutrapport.

## 2026-04-20 — Claude Code-orchestrerad med manuell trigger per steg

**Beslut:** Anders triggar varje steg med "kör nästa steg". PM läser state-fil, dispatchar, evaluerar, loggar, rapporterar. Mellan tasks (efter alla 9 observationer per task) säger PM till om `/clear`.

**Motivering:** Manuell triggning tillåter pause/resume utan idle-tid i mätningen. `/clear` per task balanserar PM-kontext-ackumulation mot overhead. Slumpad interleaved körningsordning inom task förhindrar implicit bias från att se alla Haiku-resultat i följd.

## 2026-04-22 — Named volume istället för bind-mount

**Beslut:** Docker Compose använder named volume `llm_dispatch_mariadb_data` (ej bind-mount `./docker-data/mariadb`).

**Motivering:** Docker Desktop på macOS nekar bind-mounts från `/opt/homebrew/...` utan File Sharing-konfig. Named volume kräver ingen host-konfig, är lika reproducerbart för experimentets syfte, och tar bort peer-review-friktion.

## 2026-04-22 — All experimentdokumentation i detta repo

**Beslut:** Spec, plan-filer och all experimentrelaterad dokumentation ligger i detta repo. Ingenting i bokningssidan-repot.

**Motivering:** `/superpowers`-skills (brainstorming, writing-plans, executing-plans) är globala Claude Code-plugins som respekterar working directory — ingen teknisk koppling till bokningssidan-sessionen. Att ha experimentet helt självständigt eliminerar risk för förvirring om vilka filer hör hemma var, förenklar peer-review-publicering, och gör sessionshanteringen renare. Plan 2–5 skrivs och körs i detta repo (med Claude Code startad i repots rot).

## 2026-04-22 — Separation mellan experiment-repots och mock-projektets CLAUDE.md

**Beslut:** `/CLAUDE.md` (experiment-repots) refererar öppet till experimentet, PM-rollen, runner-protokoll. `mock-project/CLAUDE.md` (kommer i plan 2) är generisk "typisk OSS-PHP-projekt"-dokumentation — ingen referens till experimentet.

**Motivering:** Subagenter läser mock-projektets `CLAUDE.md` när de dispatcha's. Om den nämner experimentet vet subagenten att den testas, vilket ändrar beteendet. Hints om "gotchas" skulle dessutom boosta svaga modeller oproportionerligt och sänka jämförelsens värde.
