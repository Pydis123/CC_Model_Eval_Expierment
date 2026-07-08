# Vilken Claude-modell ska du använda för vad? — Ett experiment

## TL;DR

- **Två testbanker, båda N=5 repliker × 4 modell-tiers:** implementation
  (8 uppgifter) och review/tyngre resonemang (8 uppgifter).
- **Implementation är LÖST — för alla fyra tiers.** Haiku, Sonnet, Opus och
  Fable klarade **5/5 på varenda uppgift.** När specen är tydlig är
  modellval en ren KOSTNADSfråga, inte en kvalitetsfråga. Haiku är
  ~40% billigare än Sonnet/Opus för identiskt resultat.
- **Review och tyngre resonemang är där det blir intressant** — där
  separerar tiers faktiskt.
- **Haiku** klarar mekanisk review galant (hittar planterade
  säkerhetsdefekter 5/5, PR-granskning 5/5) men **faller på
  resonemangstung review**: planrevision 2/5, query-budget-analys 2/5.
- **Sonnet** är arbetshästen — 5/5 på 7 av 8 review-uppgifter, inklusive
  de två Haiku missade.
- **Opus** var enda felfria tier (40/40) — men var **aldrig ensam
  vinnare**. Ingen enskild uppgift krävde Opus.
- **Fable** saknar användningsfall: matchar Sonnet på kostnad och
  kvalitet, men dess säkerhetsspärrar omdirigerar tyst
  säkerhetsuppdrag. Hård regel: aldrig säkerhetsarbete till Fable.

---

Jag körde två separata testbanker mot Claudes fyra modellnivåer (Haiku,
Sonnet, Opus, Fable) för att svara på en konkret fråga: **när är det
värt att betala för den dyrare modellen?**

Första omgången testade väl-specificerad implementation. Andra omgången
testade review och tyngre resonemang — planrevision, säkerhetsgranskning,
kodgranskning, arkitekturbeslut. Resultatet från de två bankerna är
olika på ett sätt som är själva poängen med experimentet.

## Setup

**Bank 1 — Implementation** (8 uppgifter: i18n, CRUD, N+1-fix,
migrering+backfill, refaktorering, bugfix med reproduktion, RBAC-route,
Alpine-frontend), N=5 repliker × 4 tiers = upp till 160 körningar.
Mekanisk utvärdering: tester ska passera, query-budget hållas, diff
under gränsvärde, lint grön.

**Bank 2 — Review & hård resonemang** (8 uppgifter: adversarial
planrevision, säkerhetsgranskning med planterade defekter, PR-diff-
granskning, två arkitekturbeslut, bugg utan reproduktion,
transaktionsrefaktorering, query-budget/prestanda-analys), samma
N=5 × 4 tiers. Utvärdering: findings scoras mot en committad
facit (precision/recall), arkitekturbesluten scoras av en Opus-domare
mot en ankrad rubrik, resten är mekanisk (inklusive ett
röd/grön-regressionstest för buggen utan reproduktion).

Pinnade modell-ID:n på alla fyra tiers, verifierade per dispatch, så
tysta modelluppgraderingar inte smyger in i mätningen.

## Resultat

### Bank 1 — implementation: en takeffekt

| Modell | Pass rate | Tokens (hela banken) |
|---|:---:|:---:|
| **Haiku**  | 40/40 (100%) | ~97k |
| **Sonnet** | 40/40 (100%) | ~156k |
| **Opus**   | 40/40 (100%) | ~163k |
| **Fable**  | 40/40 (100%) | ~162k |

Alla fyra tiers klarade allt. För väl-specificerad implementation —
en sekvens av explicita edits mot ett namngivet mönster — är
tiervalet en ren kostnadsfråga. Haiku är ~40% billigare än Sonnet/Opus
för samma resultat.

### Bank 2 — review & resonemang: här separerar tiers

| Uppgift | Haiku | Sonnet | Opus | Fable | Fable omdirigerad |
|---|:---:|:---:|:---:|:---:|:---:|
| Planrevision (adversarial) | 2/5 | 5/5 | 5/5 | 5/5 | 0% |
| Säkerhetsgranskning (planterade defekter) | 5/5 | 5/5 | 5/5 | N=0 | 100% |
| Kodgranskning (PR-diff) | 5/5 | 3/5 | 5/5 | 4/4 | 20% |
| Multi-tenancy (arkitekturbeslut) | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| Webhook-leverans (arkitekturbeslut) | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| Bugg utan reproduktion | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| Transaktionsrefaktorering | 5/5 | 5/5 | 5/5 | 5/5 | 0% |
| Query-budget/prestanda | 2/5 | 5/5 | 5/5 | 3/3 | 0% |

Mean tokens per körning: Haiku ~17k · Sonnet ~20k · Opus ~23k · Fable ~20k.

## Den intressanta insikten

Haiku är fortsatt fantastisk på **mekanisk** review — den hittar
planterade säkerhetsdefekter lika bra som alla andra (5/5) och klarar
PR-kodgranskning perfekt (5/5). Men den **faller** på
**resonemangstung** review: planrevision (2/5) och query-budget/N+1-
analys (2/5) kräver att hålla flera trådar i huvudet samtidigt, och
där räcker inte Haiku. Skillnaden är inte "review vs. kod" — den är
"mönstermatchning vs. flerstegsresonemang".

Sonnet är den som täcker gapet: 5/5 på både planrevision och
query-budget-analysen, plus 5/5 på 5 andra av de 8 review-uppgifterna.
Den enda uppgift Sonnet dippade på var kodgranskning (3/5) — där Haiku
istället var perfekt.

Opus var den enda tier som gick felfri genom hela review-banken
(40/40) — men aldrig som **ensam** vinnare. Sonnet matchade den på
7 av 8 uppgifter, till ungefär 15% färre tokens. Ingen uppgift i
banken *krävde* Opus. Dess mätta värde är pålitlighet **utan att
behöva känna till uppgiften i förväg** — det blint säkra valet, inte
ett korrekthetskrav. Den gamla regeln "N+1/query-budget → måste vara
Opus" är nu tillbakavisad av data: Sonnet gick 5/5 där.

Fable matchade ceiling där den fick köra fullt (planrevision,
arkitekturbesluten, buggen utan repro, refaktoreringen — alla 5/5),
men dess dual-use-säkerhetsspärrar **omdirigerade tyst** dispatchar
på säkerhetsnära uppgifter: säkerhetsgranskningen 100% omdirigerad
(noll användbara observationer alls), kodgranskningen 20%. Hård
regel: skicka aldrig säkerhetsgranskning, säkerhetsrevision eller
adversarial kodgranskning till Fable.

## Praktisk slutsats

Använd den billigaste tier som rimligen klarar uppgiften. Eskalera
vid fel — max två försök på samma tier innan du eskalerar, aldrig
"för säkerhets skull".

**Eskaleringskedjan Haiku → Sonnet → Opus** är billigast i förväntat
värde för blandade arbetsbördor — cirka **35% billigare än att köra
allt på Opus**. Haiku → Opus (hoppa över Sonnet) ligger inom 5–10%
av det och är en enklare mental modell — välj den som passar din
uppgiftsmix.

Största enskilda insikten är fortfarande att **prompt-specificitet
är en större hävstång än modellval.** En 50-ords precisering som
namnger mönstret, pekar på befintlig kod och specificerar testet kan
flytta en uppgift från "behöver Opus" till "Haiku räcker" — billigare
än att betala Opus-premien per dispatch.

## Hur du konfigurerar CLAUDE.md för att använda det här

```markdown
## Modellval

Välj billigaste tier som rimligen klarar uppgiften. Eskalera vid fail
i stället för att retrya samma tier mer än två gånger.

- **Haiku** — default för ALL väl-specificerad implementation
  (i18n, migreringar med explicit plan, RBAC-route, frontend-wiring,
  bugfixar med reproduktion). Klarar även mekanisk review (planterade
  säkerhetsdefekter, PR-diff-granskning) — men FALLER på
  resonemangstung review (planrevision, query-budget/N+1-analys).
  Skriv ut mönstret explicit i prompten; Haiku läser inte kodbasen
  proaktivt.
- **Sonnet** — arbetshästen för review och resonemang, och
  eskaleringstier för implementation. Standard för planrevision och
  query-budget/N+1-analys.
- **Opus** — toppen av eskaleringskedjan och det blint säkra valet
  för högriskkorrekthet — inte "för säkerhets skull". Ingen uppgift i
  det här experimentet krävde Opus, men arkitekturbeslut,
  cross-system-debugging och säkerhetsgranskning som mänskligt
  omdöme är fortfarande omätta kategorier.
- **Fable** — inget mätt användningsfall. Matchar Sonnet på kostnad
  och kvalitet. HÅRD REGEL: aldrig säkerhets-/adversarial-arbete —
  dess säkerhetsspärrar omdirigerar dispatchar tyst.
```

## Begränsningar

N=5 per cell är fortfarande litet — lita på mönster över flera
uppgifter, inte på en enstaka cell. Mock-projektet är syntetiskt och
utvärderaren mäter mekanisk korrekthet (tester, query-budget,
facit-precision/recall, en Opus-domares rubrik) — inte kodkvalitet
eller underhållbarhet i produktion. Fable kördes inte till fullt N i
tre av review-cellerna: en cell (säkerhetsgranskning) fick N=0 för att
*alla fem* dispatchar omdirigerades av säkerhetsspärrarna — det är
inte saknad data, det ÄR mätningen (Fable vägrar säkerhetsarbete). De
andra två cellerna landade på N=3–4 av samma skäl. Ingen av dessa
avvikelser ändrar någon slutsats: Fable var aldrig ensam vinnare
någonstans och matchade Sonnet redan i de fullt samplade cellerna.

## Resurser

- Full data: `results/results.jsonl`
- Publik sammanfattning: `README.md`
- Implementationsbankens rapport: `docs/archive/findings-v2.1.md`
- Kostnadskalkylator för din egen arbetsbörda:
  `php runner/bin/cost-calculator.php --help`

Hela experimentet är reproducerbart deterministiskt. Repo:
[länk till repo]

---

*Tänk på att det här är ett enskilt experiment med begränsat scope. Använd
det som en uppdaterad mental modell, inte som ett facit.*
