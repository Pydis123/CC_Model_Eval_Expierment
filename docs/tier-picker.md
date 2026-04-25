# Tier-picker cheat sheet

One-page decision aid for "which Claude tier should I use for this task?"

Based on `findings.md` (8 task categories, N=3, mock-project context).
Defaults — override when context disagrees. Full reasoning in
[`conclusions.md`](conclusions.md), application guide in
[`applying-findings.md`](applying-findings.md).

## At a glance

```
Task description ready
        │
        ▼
 Is the work local (1–2 files,
 named pattern, mechanical check)?
        │
   yes ─┴── no ──► Does it cross subsystems
        │           OR require discovery
        │           OR need cross-query reasoning?
        │                │
        ▼          yes ──┴── no
      HAIKU          │       │
                     ▼       ▼
                   OPUS   SONNET
                          (or Haiku
                           with a more
                           specific prompt)
```

## By task category

| If the task is… | Start with | Fallback if it fails |
|---|:---:|:---:|
| i18n / locale / translation | **Haiku** | Sonnet |
| Migration with backfill (plan explicit) | **Haiku** | Sonnet |
| Add a route + RBAC | **Haiku** | Sonnet |
| Frontend Alpine/Vue component | **Haiku** | Sonnet |
| Bugfix with reproduction | **Haiku** | Opus |
| Service-extract refactor | **Haiku** | Sonnet |
| Multi-file CRUD addition | **Sonnet** | Opus |
| N+1 / query budget reasoning | **Opus** | — |
| Architecture decision | **Opus** | — |
| Security review | **Opus** | — |
| Cross-system debugging | **Opus** | — |

## Cost mental model

| Tier | Tokens (relative) | Wall-clock (relative) | Pass rate (this experiment) |
|---|:---:|:---:|:---:|
| Haiku | 1× | 1× | 88% |
| Sonnet | 1.2–1.6× | 1.5–2× | 96% |
| Opus | 2–4× | 2–7× | 100% |

Numbers are rough — use them to sanity-check intuition, not to budget.
For real budgeting, see [`cost-calculator.php`](../runner/bin/cost-calculator.php).

## Three rules

1. **Default to Haiku.** Most coding work is Haiku-shaped.
2. **Escalate, don't retry.** Two same-tier failures = escalate or
   improve the prompt. Three same-tier retries is wasted spend.
3. **Plan before tier-pick.** A precise prompt can move a task down a
   tier. Spend prompt-engineering effort before tier-cost.

## When to ignore this sheet

- Greenfield prototyping → Opus from the start.
- High-stakes work (auth, payments, irreversible migrations) → Opus + human review.
- Unfamiliar codebase, first 20–30 dispatches → conservative defaults until you have local evidence.
- Multi-session epics or cross-repo work → not covered by this experiment; treat findings as out-of-scope.
