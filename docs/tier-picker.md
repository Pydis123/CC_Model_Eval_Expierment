# Tier-picker cheat sheet

One-page decision aid for "which Claude tier should I use for this task?"

Based on two task banks (N=5, 4 tiers each) — implementation (v2.1) and
review/hard-reasoning (Phase 2). Full public summary in
[`README.md`](../README.md); implementation-bank report at
[`docs/archive/findings-v2.1.md`](archive/findings-v2.1.md). `docs/findings.md`
does not exist for Phase 2 (the report generator needs a fix for
variable-N / rerouted cells first) — the Phase 2 numbers below are
hand-aggregated from `results/results.jsonl`.

## At a glance

```
Task description ready
        │
        ▼
 Is this WELL-SPECIFIED IMPLEMENTATION —
 a sequence of explicit edits against a
 named pattern (route, migration, component,
 refactor, bugfix with a repro)?
        │
   yes ─┴── no ──► REVIEW / HARD REASONING
        │           (plan review, security-defect
        ▼           finding, code review, query-budget,
    HAIKU            architecture, debugging, refactor)
  (cheapest tier          │
   that passes —          ▼
   escalate only    Does it need reasoning-heavy
   on failure)      judgment (plan/spec review,
                     query-budget/N+1 analysis)?
                            │
                      yes ──┴── no
                       │         │
                       ▼         ▼
                    SONNET    HAIKU
                   minimum   (still solid on
                             mechanical review —
                             seeded-defect finding,
                             PR code review —
                             escalate to Sonnet
                             on doubt)
```

Never route security-audit / security-review / adversarial code review to
**Fable** — see the table below.

## By task category

| If the task is… | Start with | Fallback if it fails |
|---|:---:|:---:|
| i18n / locale / translation | **Haiku** | Sonnet |
| Migration with backfill (plan explicit) | **Haiku** | Sonnet |
| Add a route + RBAC | **Haiku** | Sonnet |
| Frontend Alpine/Vue component | **Haiku** | Sonnet |
| Bugfix with reproduction | **Haiku** | Sonnet |
| Service-extract / transactional refactor | **Haiku** | Sonnet |
| Multi-file CRUD addition | **Haiku** | Sonnet |
| Seeded security-defect FINDING (not judgment) | **Haiku** | Sonnet |
| PR code review | **Haiku** | Sonnet (escalate on doubt — Sonnet dipped to 3/5 in Phase 2) |
| Plan / spec review (adversarial) | **Sonnet** (Haiku fails: 2/5) | Opus |
| N+1 / query-budget reasoning | **Sonnet** (Haiku fails: 2/5) | Opus |
| Architecture decision (multi-tenancy, webhook delivery) | **Sonnet** | Opus |
| Bug with no repro | **Haiku** (5/5 measured — but reasoning-shaped, one easy instance; escalate readily) | Sonnet |
| Security review as human judgment | unmeasured — treat as **Opus** blind-safe default | — |
| Cross-system debugging | unmeasured — treat as **Opus** blind-safe default | — |
| Any of the above, but security-adjacent | **never Fable** — see Fable row | — |

**Fable** — no dispatch case measured yet: matches Sonnet on cost and quality
where it engages, but its dual-use safeguards silently reroute
security-adjacent work (102 security-audit 100% rerouted, 103 code-review 20%
rerouted). Sonnet dominates it (equal cost, equal quality, no reroute risk).
**Hard rule: never route security-audit / security-review / adversarial code
review to Fable.**

## Cost mental model

**Implementation bank (v2.1): a ceiling.** Every tier passed 5/5 on every
task (40/40 each) — tier choice affects only cost, not correctness.

| Tier | Mean tokens (whole bank) | Relative to Haiku |
|---|---:|:---:|
| Haiku | ~97k | 1× |
| Sonnet | ~156k | ~1.6× |
| Opus | ~163k | ~1.7× |
| Fable | ~162k | ~1.7× |

Haiku is ~40% cheaper than Sonnet/Opus for an identical (100%) outcome.

**Review bank (Phase 2): tiers separate.** Pass rate = clean runs that passed
/ clean runs (N=5 except where noted; Fable N varies due to safeguard
rerouting).

| Task | Haiku | Sonnet | Opus |
|---|:---:|:---:|:---:|
| Plan review (adversarial) | 2/5 | 5/5 | 5/5 |
| Security-audit (seeded defects) | 5/5 | 5/5 | 5/5 |
| Code review (PR diff) | 5/5 | 3/5 | 5/5 |
| Multi-tenancy (architecture) | 5/5 | 5/5 | 5/5 |
| Webhook delivery (architecture) | 5/5 | 5/5 | 5/5 |
| Bug, no repro | 5/5 | 5/5 | 5/5 |
| Transactional refactor | 5/5 | 5/5 | 5/5 |
| Query-budget / N+1 | 2/5 | 5/5 | 5/5 |

Opus was the only tier with zero failures (40/40) but was never the *sole*
passer — Sonnet matched it on 7 of 8 tasks at ~15% fewer tokens. Mean tokens
per run: Haiku ~17k · Sonnet ~20k · Opus ~23k · Fable ~20k.

Numbers are rough — use them to sanity-check intuition, not to budget.
For real budgeting, see [`cost-calculator.php`](../runner/bin/cost-calculator.php).

## Three rules

1. **Default to Haiku for well-specified implementation.** It's a ceiling —
   every tier passes, so cheapest wins. For review/hard-reasoning, default to
   Sonnet; drop to Haiku only for the mechanical categories it's proven on
   (seeded-defect finding, PR code review) and watch for regression.
2. **Escalate, don't retry.** Haiku → Sonnet → Opus, escalate only on
   failure, never "to be safe." Two same-tier failures = escalate or improve
   the prompt. Three same-tier retries is wasted spend.
3. **Plan before tier-pick.** A precise prompt — naming the pattern, pointing
   at existing code, specifying the test — can move a task down a tier.
   Spend prompt-engineering effort before tier-cost.

## When to ignore this sheet

- Greenfield prototyping → Opus from the start.
- High-stakes work (auth, payments, irreversible migrations) → Opus + human review.
- Security-adjacent work → never Fable (see Fable row above); Opus is the
  blind-safe default where the task is judgment rather than defect-finding.
- Unfamiliar codebase, first 20–30 dispatches → conservative defaults until you have local evidence.
- Multi-session epics, cross-repo work, or genuinely Opus-specific categories
  (whole-system architecture, cross-system debugging at scale, PM/orchestration)
  → still unmeasured; treat as out-of-scope for this sheet and use Opus as the
  reasoned (not measured) default.
