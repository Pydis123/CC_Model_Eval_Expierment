# Interactive comment composer

The current comment form on `/tickets/{id}/comments` is a plain HTML
form that requires a full page reload to submit. Upgrade it to an
Alpine.js-driven composer with the following behaviors:

## Behavior

- **Optimistic UI:** when the user submits, the new comment appears
  immediately in the list (before server responds), styled slightly
  differently (e.g. reduced opacity) until the server confirms
- **Dirty-check:** if the textarea has unsubmitted content and the
  user navigates away (e.g. clicks a link that leaves the page),
  show a confirmation dialog: "You have an unsubmitted comment.
  Discard it?"
- **Character counter:** live-updating count of characters in the
  textarea, displayed below it

## Acceptance

- `templates/tickets/comments.twig` has an Alpine `x-data` component
  for the composer
- Page renders correctly for both authenticated and unauthenticated
  users (unauth users don't see the form at all)
- Smoke test asserts the rendered HTML contains the expected Alpine
  attributes
- All existing tests continue to pass
