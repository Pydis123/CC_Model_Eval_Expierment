# LLM Dispatch Experiment — Findings

**Generated:** 2026-07-06T12:55:08Z  
**Source:** results.jsonl (160 rows)  
**Bootstrap:** 1000 samples, seed=42

## Summary

Across 8 tasks and 4 model tiers (haiku, sonnet, opus, fable), Policy B (cheapest-first escalation) is estimated to cost 97,043 tokens (95% CI: 83,703–110,708) and 1,573 seconds (95% CI: 1,273–1,896) per experiment run. Probability that all tiers fail on a given task: 0.00%.

## Per-task results (Policy A — retry-only)

### 001-i18n-status-flik

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 8,842 | 143 | 1.00 |
| sonnet | 5/5 | 18,972 | 310 | 1.80 |
| opus | 5/5 | 12,118 | 144 | 1.00 |
| fable | 5/5 | 21,145 | 281 | 1.40 |

### 002-crud-ticket-tag

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 17,434 | 251 | 1.00 |
| sonnet | 5/5 | 36,896 | 492 | 1.20 |
| opus | 5/5 | 32,973 | 440 | 1.00 |
| fable | 5/5 | 33,013 | 417 | 1.00 |

### 003-n-plus-one-fix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 8,798 | 120 | 1.00 |
| sonnet | 5/5 | 5,853 | 93 | 1.00 |
| opus | 5/5 | 12,151 | 132 | 1.00 |
| fable | 5/5 | 12,367 | 162 | 1.00 |

### 004-sla-deadline-migration

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 14,479 | 189 | 1.00 |
| sonnet | 5/5 | 10,880 | 163 | 1.00 |
| opus | 5/5 | 16,957 | 207 | 1.00 |
| fable | 5/5 | 18,339 | 239 | 1.00 |

### 005-state-service-refactor

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 8,417 | 134 | 1.00 |
| sonnet | 5/5 | 6,606 | 115 | 1.00 |
| opus | 5/5 | 12,479 | 159 | 1.00 |
| fable | 5/5 | 11,344 | 164 | 1.00 |

### 006-intermittent-test-bugfix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 6,050 | 247 | 1.00 |
| sonnet | 5/5 | 25,474 | 435 | 1.00 |
| opus | 5/5 | 25,055 | 437 | 1.00 |
| fable | 5/5 | 16,870 | 237 | 1.00 |

### 007-batch-close-rbac

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 15,007 | 224 | 1.00 |
| sonnet | 5/5 | 16,173 | 213 | 1.00 |
| opus | 5/5 | 24,019 | 327 | 1.00 |
| fable | 5/5 | 24,851 | 398 | 1.00 |

### 008-comment-composer-alpine

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 5/5 | 17,830 | 263 | 1.00 |
| sonnet | 5/5 | 35,363 | 554 | 1.00 |
| opus | 5/5 | 27,284 | 390 | 1.00 |
| fable | 5/5 | 23,940 | 327 | 1.00 |

## Policy B simulation (cheapest-first escalation)

### Per-task expected cost

| Task | Expected tokens | 95% CI | Expected time (s) | 95% CI | P(max_tier_failed) |
| --- | --- | --- | --- | --- | --- |
| 001-i18n-status-flik | 8,860 | [7,227, 10,275] | 144 | [94, 168] | 0.00 |
| 002-crud-ticket-tag | 17,451 | [15,291, 20,618] | 251 | [215, 319] | 0.00 |
| 003-n-plus-one-fix | 8,868 | [7,615, 10,328] | 121 | [105, 144] | 0.00 |
| 004-sla-deadline-migration | 14,450 | [10,723, 20,372] | 189 | [118, 252] | 0.00 |
| 005-state-service-refactor | 8,430 | [7,271, 9,309] | 134 | [90, 162] | 0.00 |
| 006-intermittent-test-bugfix | 6,026 | [4,617, 6,952] | 246 | [90, 475] | 0.00 |
| 007-batch-close-rbac | 15,031 | [11,032, 18,812] | 224 | [181, 245] | 0.00 |
| 008-comment-composer-alpine | 17,927 | [11,568, 24,345] | 263 | [173, 333] | 0.00 |

### Experiment-wide totals

| Metric | Mean | 95% CI |
| --- | --- | --- |
| Total tokens | 97,043 | [83,703, 110,708] |
| Total wall-clock (s) | 1,573 | [1,273, 1,896] |
| Tasks failed (avg) | 0.00 |  |

## Reproducibility

This file is regenerated deterministically by `runner/bin/cli report`. Same input + same seed = identical output. Verify by:

```bash
runner/bin/cli report --output=/tmp/findings.md
diff docs/findings.md /tmp/findings.md
```

