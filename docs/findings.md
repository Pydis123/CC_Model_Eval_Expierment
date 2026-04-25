# LLM Dispatch Experiment — Findings

**Generated:** 2026-04-25T13:12:30Z  
**Source:** /opt/homebrew/var/www/cc/llm-dispatch-experiment/results/results.jsonl (72 rows)  
**Bootstrap:** 1000 samples, seed=42

## Summary

Across 8 tasks and 3 model tiers (haiku, sonnet, opus), Policy B (cheapest-first escalation) is estimated to cost 107,231 tokens (95% CI: 57,969–185,212) and 1,886 seconds (95% CI: 1,046–3,108) per experiment run. Probability that all three tiers fail on a given task: 0.00%.

## Per-task results (Policy A — retry-only)

### 001-i18n-status-flik

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 5,316 | 62 | 1.00 |
| sonnet | 3/3 | 4,076 | 128 | 1.00 |
| opus | 3/3 | 18,591 | 432 | 1.67 |

### 002-crud-ticket-tag

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 2/3 | 22,906 | 418 | 2.67 |
| sonnet | 3/3 | 26,695 | 501 | 2.00 |
| opus | 3/3 | 38,608 | 795 | 1.67 |

### 003-n-plus-one-fix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 1/3 | 18,912 | 221 | 2.33 |
| sonnet | 2/3 | 15,998 | 313 | 2.33 |
| opus | 3/3 | 33,728 | 544 | 2.33 |

### 004-sla-deadline-migration

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 8,366 | 95 | 1.00 |
| sonnet | 3/3 | 7,180 | 153 | 1.00 |
| opus | 3/3 | 16,630 | 435 | 1.00 |

### 005-state-service-refactor

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 9,579 | 261 | 1.33 |
| sonnet | 3/3 | 10,074 | 232 | 1.67 |
| opus | 3/3 | 9,317 | 238 | 1.00 |

### 006-intermittent-test-bugfix

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 10,919 | 246 | 1.00 |
| sonnet | 3/3 | 15,162 | 320 | 1.00 |
| opus | 3/3 | 38,132 | 699 | 1.00 |

### 007-batch-close-rbac

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 4,906 | 77 | 1.00 |
| sonnet | 3/3 | 10,927 | 312 | 1.00 |
| opus | 3/3 | 7,304 | 348 | 1.00 |

### 008-comment-composer-alpine

| Tier | Pass rate | Mean tokens | Mean wall-clock (s) | Mean iterations |
| --- | --- | --- | --- | --- |
| haiku | 3/3 | 2,210 | 46 | 1.00 |
| sonnet | 3/3 | 2,476 | 91 | 1.00 |
| opus | 3/3 | 5,009 | 96 | 1.00 |

## Policy B simulation (cheapest-first escalation)

### Per-task expected cost

| Task | Expected tokens | 95% CI | Expected time (s) | 95% CI | P(max_tier_failed) |
| --- | --- | --- | --- | --- | --- |
| 001-i18n-status-flik | 5,266 | [4,185, 7,052] | 62 | [56, 67] | 0.00 |
| 002-crud-ticket-tag | 30,626 | [14,555, 73,211] | 571 | [247, 1,180] | 0.00 |
| 003-n-plus-one-fix | 35,897 | [10,374, 93,699] | 533 | [129, 1,360] | 0.00 |
| 004-sla-deadline-migration | 8,156 | [2,422, 12,777] | 92 | [32, 158] | 0.00 |
| 005-state-service-refactor | 9,351 | [4,042, 14,668] | 263 | [136, 334] | 0.00 |
| 006-intermittent-test-bugfix | 10,851 | [8,367, 14,484] | 243 | [137, 333] | 0.00 |
| 007-batch-close-rbac | 4,878 | [3,578, 7,462] | 77 | [51, 104] | 0.00 |
| 008-comment-composer-alpine | 2,207 | [1,976, 2,662] | 46 | [30, 58] | 0.00 |

### Experiment-wide totals

| Metric | Mean | 95% CI |
| --- | --- | --- |
| Total tokens | 107,231 | [57,969, 185,212] |
| Total wall-clock (s) | 1,886 | [1,046, 3,108] |
| Tasks failed (avg) | 0.00 |  |

## Reproducibility

This file is regenerated deterministically by `runner/bin/cli report`. Same input + same seed = identical output. Verify by:

```bash
runner/bin/cli report --output=/tmp/findings.md
diff docs/findings.md /tmp/findings.md
```

