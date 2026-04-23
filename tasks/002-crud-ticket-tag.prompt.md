# Add tags to tickets

Agents need to be able to label tickets with free-form tags to group
related issues (e.g. "billing-dispute", "gdpr", "release-1.5"). Add
a simple tagging feature.

## Model

A ticket can have 0 or more tags. A tag is a string (max 50 characters).
The same tag can be applied to multiple tickets.

## Acceptance

- New table `tags` (id, name) plus pivot table `ticket_tags` (ticket_id, tag_id)
- New migrations with sequential numbers after the existing ones
- Entity `App\Domain\Entity\Tag` plus repository `App\Domain\Repository\TagRepository`
- Endpoint `POST /tickets/{id}/tags` takes `{"name": "billing-dispute"}` and
  applies the tag to the ticket (creates the tag if it doesn't exist)
- Endpoint `DELETE /tickets/{id}/tags/{tag_id}` removes the tag from the ticket
- `GET /tickets/{id}` displays the ticket's tags in the view context
- Integration tests for the repository (~4 tests) and endpoints (~4 tests)
- All existing tests continue to pass
