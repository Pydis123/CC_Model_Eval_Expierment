# Findings — v4 slice 1: Haiku 5.5 at pinned effort (2026-10-27)

`claude-haiku-5-5` on the two Phase 2 cells Haiku 4.5 could not hold
(101 plan-review, 108 query-budget, both 2/5 in Phase 2), N=5 per arm, two
arms interleaved in one queue: `haiku55` at `--effort medium` (the documented
API default) and `haiku55high` at `--effort high`. CLI 2.1.311 for all 20
runs, judge `claude-opus-4-8` as in Phase 2/v3. Raw rows:
`results/results-v4-haiku55-2026-10.jsonl`.

## Headline results

| Cell | Haiku 5.5 medium | Haiku 5.5 high | Haiku 4.5 (Phase 2 / v3) | Sonnet 5 (Phase 2) |
|---|---|---|---|---|
| 101 pass | **5/5** | 5/5 | 2/6 / 1/3 | 5/5 |
| 101 recall (mean) | **0.95** | 0.93 | 0.38-0.50 | 0.875 |
| 101 $/run (mean) | **$0.070** | $0.148 | $0.254 | $1.033 |
| 108 pass | **5/5** | 5/5 | 2/5 / 2/3 | 5/5 |
| 108 $/run (mean) | **$0.042** | $0.054 | $0.763 | $3.047 |

Every run passed on iteration 1; no reroutes, no contamination, every
iteration reported `claude-haiku-5-5`.

## Reading

1. **Haiku 5.5 closes both cells Haiku 4.5 failed.** 20/20, and on 101 its
   recall (0.95) is above Sonnet 5's (0.875) — it found the seeded defects
   Haiku 4.5 consistently missed. The global rule "skip Haiku on plan review
   and query-budget reasoning" was calibrated on Haiku 4.5 and does not hold
   for Haiku 5.5 on this evidence.
2. **High effort buys nothing here.** Same pass rate, recall slightly lower
   (0.93 vs 0.95, within noise), cost +111% on 101 and +29% on 108. The
   pre-registered question (is raising effort a cheaper fix than escalating
   to Sonnet?) is moot on these cells because medium already passes; keep
   default effort.
3. **Cost.** Medium-effort Haiku 5.5 is ~4x cheaper than Haiku 4.5 on 101
   and ~18x on 108, and 15-70x cheaper than Sonnet 5 on these cells. Costs
   are `cost_usd` (list prices, CLI-computed; verified against the price
   table to the micro-dollar, see `docs/limitations.md`). Token counts are
   not compared: Haiku 5.5's tokenizer emits ~30% more tokens per text.

## Caveats

- **No same-id drift control** in this slice. Haiku 4.5's last re-run was
  v3 (2026-07-24) on an older CLI. A jump from 2/5 to 5/5 on both cells is
  far larger than the v3 control's spread, but some harness/CLI gain cannot
  be ruled out without re-running Haiku 4.5 on CLI 2.1.311.
- **Two cells, N=5.** 101 and 108 are the cells that discriminated Haiku;
  103 (code review, where Sonnet 5 dipped) and 102 (security audit) are
  not yet measured on Haiku 5.5.
- **Sonnet 5.5, Opus 5.5 and Fable 5.1 are unmeasured.** The comparison
  above is against the July models.
