# LLM Dispatch Experiment — Findings

**Generated:** 2026-07-05T15:05:16Z  
**Source:** results.jsonl (160 rows)  
**Bootstrap:** 1000 samples, seed=42

## Summary

Across 8 tasks and 4 model tiers (haiku, sonnet, opus, fable), Policy B (cheapest-first escalation) is estimated to cost 122,854 tokens (95% CI: 71,885–206,520) and 1,800 seconds (95% CI: 1,166–2,709) per experiment run. Probability that all tiers fail on a given task: 0.00%.

## Per-task results (Policy A — retry-only)

### 001-i18n-status-flik

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/5 | 21,911 | 304 | 3.00 |
| sonnet | 4/5 | 43,312 | 448 | 2.60 |
| opus | 5/5 | 46,839 | 560 | 2.20 |
| fable | 5/5 | 33,113 | 457 | 1.80 |

### 002-crud-ticket-tag

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 24,847 | 391 | 2.00 |
| sonnet | 5/5 | 37,556 | 580 | 2.00 |
| opus | 5/5 | 46,879 | 876 | 2.00 |
| fable | 5/5 | 38,204 | 648 | 1.40 |

### 003-n-plus-one-fix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 11,465 | 180 | 1.00 |
| sonnet | 5/5 | 2,919 | 68 | 1.00 |
| opus | 5/5 | 23,042 | 291 | 1.00 |
| fable | 5/5 | 19,898 | 289 | 1.00 |

### 004-sla-deadline-migration

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 17,159 | 270 | 2.00 |
| sonnet | 5/5 | 16,857 | 341 | 2.00 |
| opus | 5/5 | 41,911 | 481 | 2.00 |
| fable | 5/5 | 34,360 | 541 | 1.40 |

### 005-state-service-refactor

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 9,643 | 147 | 1.40 |
| sonnet | 5/5 | 2,075 | 47 | 1.00 |
| opus | 5/5 | 18,015 | 257 | 1.00 |
| fable | 5/5 | 19,038 | 284 | 1.00 |

### 006-intermittent-test-bugfix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 6,667 | 128 | 1.00 |
| sonnet | 5/5 | 23,564 | 409 | 1.00 |
| opus | 5/5 | 32,857 | 462 | 1.00 |
| fable | 5/5 | 27,351 | 404 | 1.00 |

### 007-batch-close-rbac

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 7,218 | 108 | 1.00 |
| sonnet | 5/5 | 11,832 | 199 | 1.00 |
| opus | 5/5 | 23,631 | 400 | 1.00 |
| fable | 5/5 | 26,678 | 531 | 1.00 |

### 008-comment-composer-alpine

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 2,780 | 42 | 1.00 |
| sonnet | 5/5 | 16,513 | 245 | 1.00 |
| opus | 5/5 | 13,807 | 218 | 1.00 |
| fable | 5/5 | 25,958 | 417 | 1.00 |

## Policy B simulation (cheapest-first escalation)

### Per-task expected cost

| Task | Expected tokens | 95% CI | Expected time (s) | 95% CI | P(max_tier_failed) |
| --- | --- | --- | --- | --- | --- |
| 001-i18n-status-flik | 43,153 | [13,626, 122,238] | 536 | [228, 1,373] | 0.00 |
| 002-crud-ticket-tag | 24,835 | [10,743, 36,580] | 391 | [201, 528] | 0.00 |
| 003-n-plus-one-fix | 11,442 | [9,196, 13,845] | 179 | [126, 230] | 0.00 |
| 004-sla-deadline-migration | 17,210 | [2,307, 25,237] | 271 | [36, 517] | 0.00 |
| 005-state-service-refactor | 9,720 | [1,620, 15,967] | 147 | [34, 230] | 0.00 |
| 006-intermittent-test-bugfix | 6,702 | [5,691, 8,358] | 128 | [106, 159] | 0.00 |
| 007-batch-close-rbac | 7,017 | [1,069, 12,866] | 105 | [26, 212] | 0.00 |
| 008-comment-composer-alpine | 2,775 | [2,311, 3,393] | 41 | [33, 49] | 0.00 |

### Experiment-wide totals

| Metric | Mean | 95% CI |
| --- | --- | --- |
| Total tokens | 122,854 | [71,885, 206,520] |
| Total wall-clock (s) | 1,800 | [1,166, 2,709] |
| Tasks failed (avg) | 0.00 |  |

## Reproducibility

This file is regenerated deterministically by `runner/bin/cli report`. Same input + same seed = identical output. Verify by:

```bash
runner/bin/cli report --output=/tmp/findings.md
diff docs/findings.md /tmp/findings.md
```

