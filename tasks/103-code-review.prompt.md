# Review the ticket activity / bulk-close pull request

A pull request adding bulk ticket closing, per-ticket watchers, and a
recent-activity feed is ready for review before merge. The change is
committed on top of your working tree as the `review-target` ref; run
`git diff baseline..review-target` to see exactly what it changes.

Review it the way you would a real PR: read every changed file in the diff,
trace how new code is actually called (routes, controllers, repositories,
templates), and report concrete bugs — not style preferences. Pay particular
attention to authorization on new routes, correctness of any inverted or
off-by-one logic, query safety, and whether new write paths are safe under
concurrent requests.

## Output contract

Write your findings to a file named `findings.json` in your working directory, as:
{"findings": [{"file": "<path>", "line": <int>, "defect_class": "<class>", "explanation": "<why it is a defect>"}]}
Report at most 25 findings. Use `line` = the most relevant line of the defect.

## Defect taxonomy

defect_class must be one of: sqli, xss, csrf_gap, idor, authz_missing, race_condition,
n_plus_one, logic_error, off_by_one, api_contract_break, constraint_break, missing_index,
unsafe_compare, secret_logging, session_fixation, transaction_gap, rollback_wrong,
test_unfailable, backfill_race, other.
