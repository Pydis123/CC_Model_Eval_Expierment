# Intermittent test failure in RecentActivityServiceTest

`RecentActivityServiceTest::testCountsJustCreatedTicket` is flaky.
Different teammates see it pass or fail when running the suite on CI;
running it locally in isolation it usually passes. Several recent
branches have had their CI redlighted by this one test.

Find the root cause, fix it at the source (not by retrying or extending
window sizes), and document what was wrong and why the fix works.

## Acceptance

- The test passes reliably across at least 10 consecutive runs with
  no modifications to the test assertions
- The fix addresses the root cause; do not use `sleep()` or similar
  workarounds
- Any code changes outside the test itself are documented inline with
  a short comment explaining the fix
- All other tests continue to pass
