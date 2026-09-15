# Sharing a checklist freezes it

A checklist can be changed only while it is a bozza. The moment it is shared its questions, options
and sections are frozen: there is no endpoint that edits a shared checklist, and the editor opens a
shared one as a read-only preview. Changing a shared checklist means duplicating it into a new bozza
and sharing that instead.

The alternative — let the DDL keep editing, and protect the record by snapshotting each question's
text onto the answer and soft-deleting questions instead of removing them — was considered and
rejected. It survives the data loss but not the problem: two people answering the same named
checklist would have answered different questions, and nothing on the row would say so. For a
D.Lgs 81/08 record, "which form was this" has to have one answer.

## Consequences

- Publishing and assigning are one action, not two: `POST /checklists/{checklist}/share` takes the
  recipients, flips the status and writes the assignments in one transaction. A shared checklist
  with no assignees is not a state the system can reach, so nothing has to cope with one.
- Because nothing mutates after sharing, answers reference `checklist_questions.id` directly. No
  snapshot columns on the answer, no soft deletes on sections, questions or options — those tables
  are hard-deleted, and that only ever happens to a bozza.
- The structure endpoints (both the granular ones and the whole-tree reconciler) reject a shared
  checklist with 409 rather than 403: the caller is permitted, the checklist is not in a state that
  accepts the write.
- Withdrawing one person's assignment is the exception that stays mutable — it cancels the
  obligation rather than deleting it, so a compilazione already handed in survives.
- The cost lands on the DDL who spots a typo after sharing: duplicate, fix, share again, and the
  people who already answered answered the old one. Accepted deliberately.
