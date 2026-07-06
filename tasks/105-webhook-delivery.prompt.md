# Decision memo: webhook delivery subsystem

Customers want to be notified in near-real-time when their tickets are
created, change status, or receive a comment. Write a decision memo
designing a webhook delivery subsystem for these events.

Ground the memo in this codebase as it actually exists today — where these
events actually originate in the code, what infrastructure is and isn't
already available, and how the subsystem would fit the existing request
lifecycle — not in generic webhook-system advice. Cover, concretely:

- Where in the current code these events actually occur today (the specific
  methods that create tickets, change ticket status, and create comments).
- Whether any queue/async infrastructure exists in this stack today, and
  what that implies for how delivery must work.
- The tradeoff between delivering synchronously inside the request that
  triggers the event versus decoupling delivery, in terms of request
  latency and failure isolation for this app specifically.
- The delivery semantics you choose (at-least-once vs at-most-once) and why,
  including what receivers need to handle duplicates.
- A concrete retry/backoff policy and an idempotency key format.
- What happens to a delivery that exhausts its retries.
- Whether event ordering matters for these particular events, and what
  guaranteeing order would cost.
- How a slow or unreachable receiver is prevented from blocking or slowing
  down ticket/comment writes.
- A concrete persistence design for delivery state (new table/columns)
  consistent with this codebase's existing migration and repository
  conventions.
- How the subsystem integrates with the existing routing/middleware stack
  (e.g. any admin-facing configuration surface), distinguishing that from
  the outbound delivery mechanism itself.
- A clear final recommendation with justification tied to this system's
  actual stack and scale, not a generic industry answer.

Write your decision to a file named `decision-memo.md` in your working
directory. Do not sign the memo or mention which model or tool produced it.
