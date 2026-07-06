# Decision memo: multi-tenancy for the ticket system

The ticket system is moving from a single company's internal deployment to
serving multiple customer companies from one installation. Write a decision
memo recommending how to implement multi-tenancy: shared schema with a
tenant/company identifier on the relevant tables, versus schema-per-tenant
(a separate database/schema per customer).

Ground the memo in this codebase as it actually exists today — its schema,
its repository layer, its request/auth flow, and its DI wiring — not in
generic multi-tenancy advice. Cover, concretely:

- Which existing tables actually need a tenant identifier, and which don't.
- How to roll out a new tenant column against existing data without an
  outage (ordering of migration, backfill, and constraint tightening).
- The scope of repository/query changes required, named concretely.
- Where a cross-tenant data leak could occur today at the controller/route
  boundary, and whether existing middleware would catch it.
- Index implications of adding tenant scoping to hot query paths.
- For the schema-per-tenant alternative, how connection handling would work
  given how this app currently wires its database connection.
- How tenant identity would be established and carried through a request,
  from login onward.
- A clear final recommendation with justification tied to this system's
  actual scale and shape, not a generic industry answer.
- Concrete failure modes beyond the headline leak risk (e.g. a query that
  forgets the tenant filter, foreign-key behavior across tenants, test/seed
  data collisions).

Write your decision to a file named `decision-memo.md` in your working
directory. Do not sign the memo or mention which model or tool produced it.
