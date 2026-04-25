# Vilken Claude-modell ska du använda för vad? — Ett experiment

Jag körde 72 kontrollerade kodningsuppgifter mot Claudes tre modellnivåer
(Haiku, Sonnet, Opus) för att svara på en konkret fråga: **när är det värt
att betala för den dyrare modellen?**

Resultatet är intressant. Här är kortversionen.

## Setup

- 8 realistiska kodningsuppgifter i ett PHP-projekt (CRUD, refaktorering,
  bugfix, migrering, i18n, RBAC-route, Alpine-frontend, N+1-fix)
- Varje uppgift körs 3 gånger på varje modell = **72 körningar totalt**
- Mekanisk utvärdering: tester ska passera, query-budget hållas, lint vara grön
- Pinned modell-ID:n så jag fångar eventuella tysta uppgraderingar
- Tre iterationer per körning maximalt; failar den tredje räknas det som fail

## Resultat

| Modell | Pass rate | Tokens (relativt Haiku) | Tid (relativt Haiku) |
|---|:---:|:---:|:---:|
| **Haiku**  | 21/24 (88%) | 1× | 1× |
| **Sonnet** | 23/24 (96%) | 1.2–1.6× | 1.5–2× |
| **Opus**   | 24/24 (100%) | 2–4× | 2–7× |

Haiku klarar de flesta uppgifter, men **failar systematiskt på två
kategorier**: N+1-optimering (1/3 pass) och multifil-CRUD (2/3 pass).
Sonnet förbättrar marginellt. Opus klarar allt — men är 2–4× dyrare i
tokens och 2–7× långsammare i wall-clock.

## Den intressanta insikten

På 5 av 8 uppgiftstyper är Haiku helt enkelt rätt val: den klarar dem
på första försöket, kostar 50–70% mindre än Opus, och är 3–7× snabbare.
Att alltid köra Opus för att vara "säker" är att betala 2–4× för
kapacitet du inte använder.

På 1 av 8 (N+1-optimering) är Haiku otillräcklig — det handlar om att
resonera över ett *query budget* spritt i koden, och de mindre modellerna
saknar systemförståelsen. Det här är Opus territorium.

På 2 av 8 (CRUD-additionar och bugfixar med oklar reproduktion) hamnar
det i en gråzon där iteration spelar roll och Sonnet kan vara
ekonomiskt vinnande.

## Praktisk slutsats

Använd den billigaste modellen som rimligen klarar uppgiften.
Eskalera vid fel — retry samma modell mer än två gånger är slöseri.
För blandade arbetsbörder är **Haiku → Sonnet → Opus** som tre-stegs
escalation billigast i förväntat värde (~35% lägre än bara Opus, samma
0% sluttlig fail-rate).

Den största vinsten ligger inte i modellval — den ligger i
**prompt-ingenjörskapet**. En 50-ords precisering av en task-beskrivning
kan flytta uppgiften från "behöver Opus" till "Haiku räcker", med
multipla dollar i besparing per dispatch.

## Begränsningar

N=3 per cell är för få repliker för att räkna confidence-intervall
seriöst. Mock-projektet är litet och har planterade anti-mönster.
Utvärderaren mäter mekanisk korrekthet, inte kodkvalitet eller
underhållbarhet. Slutsatserna gäller för enstaka, väl-specificerade
uppgifter — inte för flersessionsprojekt eller ostrukturerad
exploration.

## Resurser

- Full data: `results/results.jsonl`
- Genererad rapport: `docs/findings.md`
- Analys per uppgift: `docs/conclusions.md`
- Praktisk tillämpning + CLAUDE.md-snuttar: `docs/applying-findings.md`
- Cheat sheet: `docs/tier-picker.md`
- Kostnadskalkylator för din egen arbetsbörda:
  `php runner/bin/cost-calculator.php --help`

Hela experimentet är reproducerbart deterministiskt. Repo:
[länk till repo]

---

*Tänk på att det här är ett enskilt experiment med begränsat scope. Använd
det som en uppdaterad mental modell, inte som ett facit.*
