# LLM Dispatch Experiment — Findings

**Generated:** 2026-04-23T19:36:28Z  
**Source:** complete_small.jsonl (12 rows)  
**Bootstrap:** 1000 samples, seed=42

## Summary

Across 2 tasks and 3 model tiers (haiku, sonnet, opus), Policy B (cheapest-first escalation) is estimated to cost 33,104 tokens (95% CI: 21,000–45,500) and 358 seconds (95% CI: 210–510) per experiment run. Probability that all tiers fail on a given task: 0.00%.

## Per-task results (Policy A — retry-only)

### task-a

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 1/2 | 12,500 | 140 | 2.00 |
| sonnet | 2/2 | 18,000 | 200 | 1.00 |
| opus | 2/2 | 30,000 | 400 | 1.00 |

### task-b

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 2/2 | 11,750 | 120 | 1.50 |
| sonnet | 2/2 | 17,000 | 190 | 1.00 |
| opus | 2/2 | 29,000 | 390 | 1.00 |

## Policy B simulation (cheapest-first escalation)

### Per-task expected cost

| Task | Expected tokens | 95% CI | Expected time (s) | 95% CI | P(max_tier_failed) |
| --- | --- | --- | --- | --- | --- |
| task-a | 21,339 | [10,000, 33,000] | 238 | [100, 380] | 0.00 |
| task-b | 11,765 | [11,000, 12,500] | 120 | [110, 130] | 0.00 |

### Experiment-wide totals

| Metric | Mean | 95% CI |
| --- | --- | --- |
| Total tokens | 33,104 | [21,000, 45,500] |
| Total wall-clock (s) | 358 | [210, 510] |
| Tasks failed (avg) | 0.00 |  |

## Reproducibility

This file is regenerated deterministically by `runner/bin/cli report`. Same input + same seed = identical output. Verify by:

```bash
runner/bin/cli report --output=/tmp/findings.md
diff docs/findings.md /tmp/findings.md
```

