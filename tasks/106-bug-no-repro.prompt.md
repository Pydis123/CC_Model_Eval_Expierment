# Reopened tickets sometimes come back already past their SLA deadline

Support has flagged a recurring complaint: when an agent reopens a
ticket, its SLA deadline is sometimes already in the past. The ticket
lands back in the queue looking overdue the instant it is reactivated,
even though the customer has only just come back to us and the clock
should be running fresh. It does not happen for every reopened ticket,
which has made it awkward to reproduce reliably from a single example.

Track down the root cause, fix it at the source, and add a regression
test that captures the wrong behaviour.

## Acceptance

- A new or updated test reproduces the fault: it fails against the
  current code and passes once your fix is in place, without any change
  that merely papers over the symptom.
- The fix corrects the reopen path itself, not the test, and does not
  regress the cases that already behave correctly.
- All existing tests continue to pass.
