# Vilken Claude-modell ska du använda för vad? — Ett experiment

> **Uppdaterad okt 2026 (v4).** Experimentet har körts om på den senaste
> modelluppställningen (Haiku 5.5 / Sonnet 5.5 / Opus 5.5 / Fable 5.1).
> Slutsatserna nedan har flyttats — se avsnittet
> [Vad som ändrats till v4](#vad-som-ändrats-till-v4-okt-2026). De detaljerade
> tabellerna i brödtexten visar den tidigare omgången (Phase 2, Claude-5),
> som v4 nu överträffar på pass-rate.

## TL;DR (aktuellt, v4)

- **Två testbanker, N=5 repliker × 4 modell-tiers:** implementation
  (8 uppgifter) och review/tyngre resonemang (8 uppgifter).
- **Implementation är LÖST — för alla fyra tiers.** Haiku, Sonnet, Opus och
  Fable klarade **5/5 på varenda uppgift.** När specen är tydlig är
  modellval en ren KOSTNADSfråga, inte en kvalitetsfråga.
- **Review-banken separerade tiers när den först kördes — men inte längre.**
  På Claude-5-uppställningen föll Haiku på resonemangstung review (2/5). På
  5.5-uppställningen klarar **alla fyra tiers varje diskriminerande uppgift
  (80/80)**. Haiku 5.5 täpper igen just de luckor Haiku 4.5 föll på, till
  lägsta kostnad.
- **Kostnad är nu den enda riktiga skillnaden.** Haiku 5.5 är golvet med bred
  marginal (13–45× billigare än Opus/Fable); Sonnet 5.5 och Opus 5.5 är
  60–85% billigare än sina föregångare.
- **Säkerhetsbeteendet vände.** I v3 var Opus den enda utan spärr-problem;
  i v4 **hårdblockerar hela 5.5-generationen offensiva formuleringar** (eget
  API-fel), Opus inräknad. Defensiva granskningar går fortfarande igenom.
- **Fable** saknar fortfarande användningsfall: dyrast, ingen kvalitetsvinst,
  och omdirigerar/blockerar säkerhetsarbete. Hård regel kvar: aldrig
  säkerhetsarbete till Fable.

---

Jag körde två separata testbanker mot Claudes fyra modellnivåer (Haiku,
Sonnet, Opus, Fable) för att svara på en konkret fråga: **när är det
värt att betala för den dyrare modellen?**

Första omgången testade väl-specificerad implementation. Andra omgången
testade review och tyngre resonemang — planrevision, säkerhetsgranskning,
kodgranskning, arkitekturbeslut. Resultatet från de två bankerna är
olika på ett sätt som är själva poängen med experimentet.

## Vad som ändrats till v4 (okt 2026)

Experimentet har körts fem gånger, från en tidig trenivå-pilot till en full
omkörning på den aktuella uppställningen. Så här har bilden förändrats:

| Omgång | Datum | Uppställning | Omfattning | Huvudresultat |
|---|---|---|---|---|
| **v1** | 2026-04 | Haiku 4.5 / Sonnet 5 / Opus 4.8 (3 tiers) | båda banker, N=3 | Tidig pilot; validerade riggen. Arkiverad. |
| **v2.1** | 2026-07-06 | + Fable 5 (4 tiers) | implementation, N=5 | **Tak** — alla 5/5 på allt. Tier = kostnad. |
| **Phase 2** | 2026-07-08 | samma 4 tiers | review, N=5 | **Tiers separerar.** Haiku faller 2/5 på plan/query-budget; Opus enda felfria; Fable omdirigerar säkerhet 100%. |
| **v3** | 2026-07-24 | Opus `4.8 → 5` | 4 celler + probe | Opus 5 ren drop-in (20/20). Probe: Opus 5 **0/20** omdirigerad, Fable 5 16/20, Sonnet 5 nekade 4/20. |
| **v4** | 2026-10-27/28 | **hela uppställningen → 5.5** | 4 celler + probe | **Nytt tak (80/80).** Haiku 5.5 klarar resonemangscellerna 4.5 föll på, billigast. Probe **vände**: hela 5.5-gen hårdblockerar offensiva formuleringar — Opus inräknad. |

**Kärnan i v4:** de uppgifter som en gång skilde modellerna åt gör det inte
längre — modellerna har kommit ikapp testet. Det som återstår att välja på är
kostnad (där Haiku 5.5 vinner stort) och säkerhetsbeteende (där hela
generationen nu blockerar offensiva formuleringar men släpper igenom
defensiva granskningar). Detaljer i `docs/findings-v4-part1.md` och
`docs/findings-v4-part2.md`.

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

### Bank 2 — review & resonemang (Phase 2, Claude-5-uppställningen)

Så här såg det ut när banken *först* kördes — det var här tiers separerade:

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

### Bank 2 — samma uppgifter på 5.5-uppställningen (v4)

Omkört på de fyra celler som skilde tiers i Phase 2. Pass · recall (där det
scoras) · snittkostnad per körning (listpris):

| Uppgift | Haiku 5.5 | Sonnet 5.5 | Opus 5.5 | Fable 5.1 |
|---|---|---|---|---|
| Planrevision | 5/5 · 0,95 · $0,07 | 5/5 · 0,98 · $0,36 | 5/5 · 1,00 · $0,67 | 5/5 · 1,00 · $2,46 |
| Säkerhetsgranskning | 5/5 · 0,85 · $0,05 | 5/5 · 0,77 · $0,34 | 5/5 · 0,88 · $0,63 | 5/5 · 0,78 · $2,24\* |
| Kodgranskning | 5/5 · 0,88 · $0,02 | 5/5 · 0,80 · $0,20 | 5/5 · 0,88 · $0,35 | 5/5 · 0,90 · $1,41 |
| Query-budget | 5/5 · $0,04 | 5/5 · $0,47 | 5/5 · $0,71 | 5/5 · $3,45 |

\* Fable 5.1 omdirigerade tyst 1 av 5 säkerhetskörningar till `claude-opus-4-8`
(gav ändå ett användbart, godkänt svar).

Alla celler är nu 5/5. Haiku 5.5 klarar planrevision och query-budget — de två
Haiku 4.5 föll på — och var enda modellen som löste query-budget på första
försöket varje gång. Recall ligger tätt (Opus 5.5 högst på säkerhet, 0,88;
Haiku 5.5 nära på 0,85). **Säkerhetsproben vände:** alla tre testade modeller
klarar den defensiva formuleringen men hårdblockerar de offensiva (eget
API-fel) — där Opus 5 i v3 körde allt rent.

## Den intressanta insikten (Phase 2)

*Det här var analysen när review-banken först kördes, på Claude-5-
uppställningen. På 5.5-uppställningen (v4) klarar Haiku bägge de cellerna som
beskrivs som svaga nedan — se v4-tabellen ovan. Resonemanget om* varför *de
skilde sig åt är ändå den intressanta delen.*

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

- **Haiku** (`claude-haiku-5-5`) — default för ALL väl-specificerad
  implementation (i18n, migreringar med explicit plan, RBAC-route,
  frontend-wiring, bugfixar med reproduktion). På 5.5 klarar den även
  resonemangstung review (planrevision, query-budget) som 4.5 föll på,
  till lägsta kostnad — så den är nu ett rimligt förstaval även för
  granskning. Skriv ändå ut mönstret explicit i prompten; jämför tiers
  i kronor, inte tokens (ny tokenizer ger ~30% fler tokens).
- **Sonnet** (`claude-sonnet-5-5`) — arbetshästen för review, och
  eskaleringstier för implementation. Dess ensamrätt har krympt när
  Haiku 5.5 nu täcker plan- och query-budget-review.
- **Opus** (`claude-opus-5-5`) — toppen av eskaleringskedjan och det
  blint säkra valet för högriskkorrekthet — inte "för säkerhets skull".
  OBS: Opus 5.5 är INTE längre spärr-fri på säkerhet — den
  hårdblockerar offensiva formuleringar som resten av generationen.
  Defensiva granskningar går igenom.
- **Fable** (`claude-fable-5-1`) — inget mätt användningsfall; dyrast,
  ingen kvalitetsvinst. HÅRD REGEL: aldrig säkerhets-/adversarial-
  arbete — tyst omdirigering plus öppna blockeringar.
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

- Full data: `results/*.jsonl`
- Publik sammanfattning: `README.md`
- Aktuella findings (v4): `docs/findings-v4-part1.md`, `docs/findings-v4-part2.md`
- Implementationsbankens rapport: `docs/archive/findings-v2.1.md`
- Kostnadskalkylator för din egen arbetsbörda:
  `php runner/bin/cost-calculator.php --help`

Hela experimentet är reproducerbart deterministiskt. Repo:
[länk till repo]

---

*Tänk på att det här är ett enskilt experiment med begränsat scope. Använd
det som en uppdaterad mental modell, inte som ett facit.*
