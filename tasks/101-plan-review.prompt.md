# Review the SLA escalation implementation plan

An implementation plan for adding SLA escalation tracking has been drafted
against this codebase and is about to move into implementation. Before that
happens, it needs a hard technical review: does every claim the plan makes
about the schema, the existing code, and the existing tests actually hold?

The plan is `PLAN.md`, provided in your working area. Read it in full, then
check its claims against the real migrations, entities, repositories,
controllers, routes, and tests it references — not just against what sounds
plausible. Look specifically for places where the plan asserts something
about column names, indexes, unique constraints, return shapes, route
guards, rollback behavior, or test coverage that the actual codebase
contradicts.

## Output contract

Write your findings to a file named `findings.json` in your working directory, as:
{"findings": [{"file": "<path>", "line": <int>, "defect_class": "<class>", "explanation": "<why it is a defect>"}]}
Report at most 25 findings. Use `line` = the most relevant line of the defect.

For this review, set `file` to `PLAN.md` and `line` to the line in the plan
where the flawed claim is made. Put the concrete contradiction with the real
codebase (file, symbol, and why it breaks) in `explanation`.

## Defect taxonomy

defect_class must be one of: sqli, xss, csrf_gap, idor, authz_missing, race_condition,
n_plus_one, logic_error, off_by_one, api_contract_break, constraint_break, missing_index,
unsafe_compare, secret_logging, session_fixation, transaction_gap, rollback_wrong,
test_unfailable, backfill_race, other.
