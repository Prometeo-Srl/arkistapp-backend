# A checklist's structure is saved as one tree, reconciled by client-supplied id

The slice already ships granular endpoints — `POST /checklists/{checklist}/sections`,
`PATCH /checklists/questions/{question}`, `DELETE /checklists/sections/{section}` — one request per
edit. The builder they serve is not a series of edits: it is a single editing session over a nested
tree that ends at one `salva`. Replaying that session as N ordered requests means the app holds
server-assigned ids while the user is still typing, and a request dropped in the middle leaves a
half-written checklist on the server that no screen can show or repair. So the builder saves through
`PUT /checklists/{checklist}/structure`, which takes sections, questions and options as one nested
payload and reconciles it against what is stored.

Reconciliation keys on ids the **client** generates (UUIDs), not on ids the server hands back. That
is the part worth recording: it is why `checklist_sections`, `checklist_questions` and
`checklist_options` carry a uuid the app chose. Without it the save is not idempotent — a retry
after a timeout duplicates every row, because the client has no way to say "this is the same
question I sent you before". With it, the same payload sent twice is the same tree, and a retry is
free.

## Consequences

- Rows present in the payload are updated, rows absent from it are deleted. Deletion is by omission,
  so the payload is authoritative: a partial tree destroys what it omits. This is safe only because
  a checklist is immutable once shared (ADR-0005) — the endpoint rejects anything but a bozza.
- `position` is sent by the client, not inferred from array order. Cross-section drag and drop moves
  a question by changing `(checklist_section_id, position)` in the same payload as everything else,
  which the granular endpoints could only express as a delete plus a create.
- The granular endpoints stay. They are not deprecated and not duplicated work: they remain the
  right shape for anything that changes one thing at a time, and the reconciler is not a general
  write path for other callers.
- Two write paths to the same tables means one place to keep the invariants. Both go through the
  same validation and the same "bozza only" guard; neither gets its own copy.
