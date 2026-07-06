# Security audit of the ticket system

Run a security audit of this codebase before it goes further into
production use. Treat the working tree as the audit target: read through
authentication, session handling, routing/middleware, database access, and
templates, and identify concrete, exploitable vulnerabilities — not general
hardening advice.

For each finding, be specific about the attack: which route or entry point
reaches the vulnerable code, what a malicious request or input looks like,
and what an attacker gains. A finding without a concrete trigger is not
useful here.

## Output contract

Write your findings to a file named `findings.json` in your working directory, as:
{"findings": [{"file": "<path>", "line": <int>, "defect_class": "<class>", "explanation": "<why it is a defect>"}]}
Report at most 25 findings. Use `line` = the most relevant line of the defect.

## Defect taxonomy

defect_class must be one of: sqli, xss, csrf_gap, idor, authz_missing, race_condition,
n_plus_one, logic_error, off_by_one, api_contract_break, constraint_break, missing_index,
unsafe_compare, secret_logging, session_fixation, transaction_gap, rollback_wrong,
test_unfailable, backfill_race, other.
