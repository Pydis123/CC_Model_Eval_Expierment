# Findings — v4 part 1: the Sep-Oct 2026 lineup on 101, 103, 108 (2026-10-27)

56 runs (`results/results-v4-part1-2026-10.jsonl`) plus the 10 Haiku 5.5
medium-effort runs on 101/108 from slice 1 (`findings-v4-haiku55.md`).
CLI 2.1.311 throughout, judge `claude-opus-4-8`, effort pinned to each
model's documented API default. 102 (security audit) and the safeguard probe
are part 2 and not covered here.

## Headline results

Pass rate, mean recall (101/103 only) and mean list-price `cost_usd` per run.

| Cell | Haiku 5.5 | Sonnet 5.5 | Opus 5.5 | Fable 5.1 |
|---|---|---|---|---|
| 101 plan review | 5/5 · 0.95 · $0.07 | 5/5 · 0.98 · $0.36 | 5/5 · 1.00 · $0.67 | 5/5 · 1.00 · $2.46 |
| 103 code review | 5/5 · 0.88 · $0.02 | 5/5 · 0.80 · $0.20 | 5/5 · 0.88 · $0.35 | 5/5 · 0.90 · $1.41 |
| 108 query budget | 5/5 · $0.04 | 5/5 · $0.47 | 5/5 · $0.71 | 5/5 · $3.45 |
| 108 iterations used | 1,1,1,1,1 | 2,2,2,2,2 | 2,1,1,2,1 | 1,2,1,2,2 |

Previous generation on the same cells, for reference:

| Cell | Haiku 4.5 | Sonnet 5 | Opus 5 | Fable 5 |
|---|---|---|---|---|
| 101 | 2/6, then 1/3 (v3), then **0/3** (v4 control) | 5/5 · $1.03 | 5/5 · $1.67 | 5/5 · $2.74 |
| 103 | 5/5 · rec 0.63 | **3/5** · rec 0.45 | 5/5 · rec 0.90 | 5/5 · 20% rerouted |
| 108 | 2/5, then 2/3 (v3), then **2/3** (v4 control) | 5/5 · $3.05 | 5/5 · $3.39 | 3/3 |

No run in part 1 was rerouted, refused or contaminated; every iteration
reported the requested model id.

## Reading

1. **Every new model passes every non-security cell (60/60).** These three
   cells no longer separate the tiers. They were the cells that did in
   Phase 2, so on non-security review/analysis work the bank is now at a
   ceiling, as the implementation bank already was in v2.1.
2. **The drift control holds.** Haiku 4.5 on CLI 2.1.311 scored 0/3 on 101
   (recall 0.42) and 2/3 on 108, at or below its Phase 2/v3 level. The
   harness did not get easier; Haiku 5.5's 10/10 on those cells is the model.
3. **Haiku 5.5 is the cost floor by a wide margin.** It is 5-10x cheaper than
   Sonnet 5.5 and 10-20x cheaper than Opus 5.5 on these cells, with recall
   in the same band (101: 0.95 vs 0.98-1.00; 103: tied with Opus 5.5, above
   Sonnet 5.5). It was the only model that solved 108 on the first
   iteration every time.
4. **The 5.5 generation is much cheaper than its predecessors.** Sonnet 5.5
   costs 65-85% less than Sonnet 5 on these cells; Opus 5.5 costs 60-80%
   less than Opus 5. Both are also faster on wall clock (1-2.5 min per run).
5. **Fable 5.1 adds nothing measurable here** at 4-7x Opus 5.5's cost. It
   did not reroute on 103 (Fable 5 rerouted 20% there); its security
   behaviour is part 2.

## Caveats

- N=5 per cell, three cells, one codebase. Ceilings hide differences that
  harder tasks might show; recall differences inside one cell (for example
  103: 0.80-0.90) are within run-to-run noise at this N.
- Effort is pinned to each model's own default, so "Sonnet 5.5" means Sonnet
  5.5 at `high` and "Opus 5.5" means Opus 5.5 at `medium`. This matches how
  they are dispatched in practice; it is not an effort-matched comparison.
- Cost is list price from the CLI's `total_cost_usd`; runs were made on a
  subscription. Judge cost is excluded.
- 102 and the safeguard probe (part 2) are still open, so nothing here
  changes the security-review routing rules.
