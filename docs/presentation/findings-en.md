# Which Claude model should you use for what? — An experiment

I ran 72 controlled coding tasks against Claude's three model tiers
(Haiku, Sonnet, Opus) to answer one practical question: **when is it
worth paying for the more expensive model?**

The result is more interesting than I expected. Short version below.

## Setup

- 8 realistic coding tasks in a PHP project (CRUD, refactor, bugfix,
  migration, i18n, RBAC route, Alpine frontend, N+1 query fix)
- Each task run 3× on each tier = **72 total dispatches**
- Mechanical evaluation: tests pass, query budget holds, lint clean,
  expected files exist
- Pinned model IDs to catch silent upgrades during the run
- 3 iterations per dispatch max; if iteration 3 fails, the run fails

## Results

| Tier | Pass rate | Tokens (relative to Haiku) | Time (relative to Haiku) |
|---|:---:|:---:|:---:|
| **Haiku**  | 21/24 (88%)  | 1× | 1× |
| **Sonnet** | 23/24 (96%)  | 1.2–1.6× | 1.5–2× |
| **Opus**   | 24/24 (100%) | 2–4× | 2–7× |

Haiku clears most tasks but **fails systematically on two categories**:
N+1 optimization (1/3 pass) and multi-file CRUD (2/3 pass). Sonnet
improves marginally. Opus clears everything — at 2–4× the tokens and
2–7× the wall-clock.

## The interesting finding

On 5 of 8 task categories, Haiku is simply the right choice: it gets
them on the first try, costs 50–70% less than Opus, and is 3–7× faster.
Defaulting to Opus "to be safe" is paying 2–4× for capability you don't
use.

On 1 of 8 (N+1 optimization), Haiku is structurally insufficient — the
task is about reasoning across a query budget spread through the code,
and the smaller tiers lack the systemic view. This is Opus territory.

On 2 of 8 (multi-file CRUD, bugfix-with-unclear-repro), the work falls
into a gray zone where iteration matters and Sonnet can be the
economically winning choice.

## Practical takeaway

Use the cheapest tier that has a reasonable chance of passing. Escalate
on failure — retrying the same tier more than twice is wasted spend.
For varied workloads, **Haiku → Sonnet → Opus** as a 3-tier escalation
is cheapest in expectation (~35% under all-Opus, same 0% final fail
rate).

The biggest cost lever isn't tier choice — it's **prompt engineering**.
A 50-word tightening of a task brief can move work from "needs Opus" to
"Haiku is fine," saving multiple dollars per dispatch.

## Limitations

N=3 per cell is too few replicates for proper confidence intervals.
The mock project is small and has planted anti-patterns. The evaluator
measures mechanical correctness, not code quality or maintainability.
Findings apply to single, well-specified tasks — not multi-session work
or open-ended exploration.

## Resources

- Raw data: `results/results.jsonl`
- Generated report: `docs/findings.md`
- Per-task analysis: `docs/conclusions.md`
- Practical application + CLAUDE.md snippets: `docs/applying-findings.md`
- Cheat sheet: `docs/tier-picker.md`
- Cost calculator for your own workload:
  `php runner/bin/cost-calculator.php --help`

The entire experiment is deterministically reproducible. Repo:
[link to repo]

---

*This is one experiment with limited scope. Use it as an updated mental
model, not as ground truth.*
