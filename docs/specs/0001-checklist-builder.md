# 0001 — Checklist builder, condivisione and compilazione

Status: done
Source: XD prototype screens in `docs/app/xd/`, the designer's notes, and a grilling session on
2026-09-15. Decisions recorded in ADR-0004 and ADR-0005; vocabulary in `CONTEXT.md`.

## Problem Statement

A DDL has to run safety checks that the law requires and keep a record of who answered what. Today
the platform can hold a checklist — the tables and endpoints exist — but nothing in the app can
build one. The endpoints were shaped for a caller that edits one row at a time, while the screens
the DDL actually gets are a single editing session over a nested form: sections, questions inside
them, options inside those, dragged around and retyped until the DDL is happy and presses save
once.

Three things follow from that mismatch, and all three are the problem:

1. There is no way to author a checklist from the app at all.
2. If the app drove the existing per-row endpoints, a dropped request mid-session would leave a
   half-written checklist on the server that no screen can display or repair, and a retry would
   duplicate rows rather than replace them.
3. The existing model lets a published checklist keep changing underneath people who are answering
   it, so two workers can answer "the same" named checklist and have been asked different questions,
   with nothing on the record saying so. For a D.Lgs 81/08 record that is not an acceptable state.

Separately, several columns in the slice encode requirements nobody has: a recurrence frequency,
a per-option non-conformity flag, a stored PDF path, numeric and image question types. Answers in
this product are free — nothing is scored, passed or failed — and checklists do not recur.

## Solution

The DDL opens the checklist area, taps the plus, and writes a questionnaire: a title, then sections,
each with a rich-text heading, each holding questions. A question has a label, an optional image the
DDL pins to it, a type (scelta singola, scelta multipla, descrizione, data, orario), an optional
free-text note box for whoever answers, an optional file attachment for whoever answers, and an
obbligatorio switch. Choice questions carry options, each with a label and an optional image.
Everything reorders by drag, including moving a question from one section to another. Questions
duplicate from their own menu.

Pressing **salva** stores the whole thing as a **bozza** — grey icon in the list, visible only to
the people who may author. Leaving the editor by the back arrow raises a confirm asking whether to
save what has been done so far.

Pressing the blue arrow opens the **condivisione** step: the DDL picks named people from the
workspace and confirms. That one action publishes the checklist and creates their **assegnazioni**
together, in one transaction. The icon turns blue. From that moment the questions are frozen —
reopening a shared checklist shows a read-only preview, and changing it means duplicating it into a
new bozza.

Each assignee sees the checklist in their own list, opens it as a paged wizard — one section per
page, a progress bar, avanti and indietro — answers it in one sitting and finalises. That
**compilazione** is handed over complete; there is no half-finished one to come back to.

Anyone who can see a shared checklist can download it as a PDF of the questionnaire itself,
rendered on demand, images included.

## User Stories

### Authoring a bozza

1. As a DDL, I want to create a new empty checklist from the checklist list, so that I can start
   writing a safety check without leaving the app.
2. As a DDL, I want to give the checklist a title, so that my colleagues can tell it apart from the
   others in the list.
3. As a DDL, I want to give the checklist a description, so that the people answering understand
   what the check is for before they start.
4. As a DDL, I want to add a section, so that I can group related questions into one page of the
   questionnaire.
5. As a DDL, I want to write a section heading with bold, italic and underline, so that the heading
   reads the way the printed safety documentation reads.
6. As a DDL, I want to add a question to a section, so that I can ask one thing at a time.
7. As a DDL, I want to write a question label with bold, italic and underline, so that I can
   emphasise the part of a long legal question that matters.
8. As a DDL, I want to choose a question's type from scelta singola, scelta multipla, descrizione,
   data and orario, so that the answer I get back is the shape I need.
9. As a DDL, I want to add options to a choice question, so that the answer is constrained to the
   answers I consider valid.
10. As a DDL, I want to attach an image to an option, so that a worker can recognise a piece of
    equipment by sight rather than by name.
11. As a DDL, I want to attach an image to a question, so that I can show the situation being
    assessed.
12. As a DDL, I want to mark a question obbligatorio, so that nobody can hand in the checklist
    without answering it.
13. As a DDL, I want to allow a free-text note on a question, so that the person answering can
    explain an answer the options do not capture.
14. As a DDL, I want to allow a file attachment on a question, so that the person answering can
    supply a photo as evidence.
15. As a DDL, I want to reorder options within a question by dragging, so that the most likely
    answer sits first.
16. As a DDL, I want to reorder questions within a section by dragging, so that the questionnaire
    follows the order of the physical inspection.
17. As a DDL, I want to drag a question from one section into another, so that I can reorganise the
    questionnaire without retyping it.
18. As a DDL, I want to reorder sections, so that the wizard pages come in the right order.
19. As a DDL, I want to duplicate a question, so that a near-identical question costs me one edit
    rather than a retype.
20. As a DDL, I want to delete a question, so that I can drop one I no longer need.
21. As a DDL, I want to delete a section and be warned that every question inside it goes with it,
    so that I do not destroy work by mistake.
22. As a DDL, I want to preview the questionnaire as the worker will see it, so that I can check it
    reads correctly before anyone gets it.
23. As a DDL, I want to save the checklist as a bozza at any point, so that I can finish writing it
    later.
24. As a DDL, I want to be asked whether to save when I leave the editor with unsaved changes, so
    that I do not lose what I have just written.
25. As a DDL, I want my save to succeed even if the first attempt timed out and the app retried, so
    that I do not end up with a duplicated questionnaire.
26. As a DDL, I want a bozza to be visible to my colleagues who also author checklists, so that we
    can hand work over.
27. As a DDL, I want a bozza to be invisible to the workers, so that nobody answers a questionnaire
    I have not finished.
28. As a DDL, I want to tell a bozza from a shared checklist at a glance in the list, so that I know
    what still needs my attention.
29. As a DDL, I want to duplicate a whole checklist, so that I can use an existing one as a starting
    point instead of building a template system.
30. As a DDL, I want to delete a bozza, so that abandoned drafts do not clutter the list.
31. As a DDL, I want to search the checklist list by title, so that I can find one in a long list.
32. As a DDL, I want to sort the list by last modified, so that what I worked on most recently is at
    the top.
33. As a DDL, I want each row to show who added it and when, so that I know whose work it is.

### Condivisione

34. As a DDL, I want to move from the editor to a sharing step, so that writing and distributing are
    separate deliberate acts.
35. As a DDL, I want to pick the people who must answer from the workspace's members, so that the
    obligation lands on named individuals I chose.
36. As a DDL, I want sharing to publish the checklist and assign it in one action, so that I can
    never end up with a published checklist nobody can answer.
37. As a DDL, I want to set a due date when I share, so that the people answering know when it is
    expected.
38. As a DDL, I want the checklist to become read-only once shared, so that everyone who answers it
    answered the same questionnaire.
39. As a DDL, I want to be told plainly that a shared checklist cannot be edited, so that I am not
    left wondering why the editor will not accept my change.
40. As a DDL, I want to open a shared checklist and see the questionnaire as a preview, so that I
    can re-read what I sent.
41. As a DDL, I want to duplicate a shared checklist into a new bozza, so that I have a way to
    correct a mistake I only spotted after sharing.
42. As a DDL, I want to add more people to an already shared checklist, so that someone who joined
    late can still be asked.
43. As a DDL, I want to withdraw one person's assegnazione, so that somebody who should not have
    been asked stops being asked.
44. As a DDL, I want a withdrawn assegnazione to keep whatever that person already answered, so that
    withdrawing an obligation never destroys a record.

### Compilazione

45. As a worker, I want to see the checklists assigned to me, so that I know what I have been asked
    to do.
46. As a worker, I want to see only what was assigned to me, so that I am not shown the company's
    other safety paperwork.
47. As a worker, I want to see the due date of each assegnazione, so that I can plan around it.
48. As a worker, I want to open an assigned checklist as a paged wizard, one section per page, so
    that a long questionnaire is not one intimidating scroll.
49. As a worker, I want a progress indicator, so that I know how much is left.
50. As a worker, I want to move forward and back between sections, so that I can correct an earlier
    answer before handing it in.
51. As a worker, I want to answer a scelta singola by picking one option, so that my answer is
    unambiguous.
52. As a worker, I want to answer a scelta multipla by picking several options, so that I can record
    everything that applies.
53. As a worker, I want to see the images attached to options, so that I can identify equipment I
    know by sight.
54. As a worker, I want to see the image attached to a question, so that I understand what is being
    asked about.
55. As a worker, I want to type free text where the question asks for a description, so that I can
    describe an anomaly in my own words.
56. As a worker, I want to pick a date or a time where the question asks for one, so that the record
    is precise.
57. As a worker, I want to add a note to a question that allows one, so that I can qualify an answer
    the options do not fit.
58. As a worker, I want to attach a photo to a question that allows one, so that I can evidence what
    I found.
59. As a worker, I want to be stopped from finalising while an obbligatorio question is unanswered,
    and told which one, so that I do not have to hunt for what is missing.
60. As a worker, I want to finalise the checklist in one action at the end, so that handing it in is
    a single deliberate act.
61. As a worker, I want to be unable to answer the same assegnazione twice, so that there is exactly
    one record of what I said.
62. As a worker, I want to read back my own compilazione after finalising, so that I can confirm
    what I submitted.
63. As a worker, I want to download the questionnaire as a PDF, so that I can carry it onto a site
    where the app is awkward to use.

### Oversight

64. As a DDL, I want to see who has been assigned a checklist, so that I know the obligation reached
    the right people.
65. As a DDL, I want to see who has started and who has finished, so that I can chase the ones who
    have not.
66. As a DDL, I want to read an individual compilazione, so that I can act on what was reported.
67. As a DDL, I want to download the questionnaire as a PDF with its images, so that I can file it
    with the rest of the safety documentation.
68. As a Prometeo Operator, I want cross-tenant read access to a client company's checklists, so
    that I can help when they call — subject to the operator rules already in force.

### Boundaries

69. As a worker in company A, I want a checklist belonging to company B to be invisible and
    unreachable to me even if I guess its id, so that the tenancy boundary holds.
70. As a worker whose membership was archived, I want to lose access to the checklists I was
    assigned, so that leaving the company ends every derived permission.
71. As a worker, I want to be refused when I try to answer an assegnazione belonging to somebody
    else, so that nobody can answer in my name or I in theirs.

## Implementation Decisions

### Vocabulary

The glossary terms are **Checklist**, **Bozza**, **Condivisione**, **Assegnazione** and
**Compilazione** (`CONTEXT.md`, Monitoring section). English class and column names stay as they
are (`Checklist`, `ChecklistAssignment`, `ChecklistSubmission`), per the project's language rule;
the Italian terms govern user-facing strings, spec prose and how we talk about the feature.

The previous glossary entry described a checklist the company fills in *periodically* and assigns to
*figures or people*. Both are now wrong and the entry has been rewritten: a checklist is authored
once, shared once, does not recur, carries no right answers, and is assigned only to named people.

### Structure is saved as one tree (ADR-0004)

A new `PUT /checklists/{checklist}/structure` accepts sections, questions and options as one nested
payload and reconciles it against what is stored. Reconciliation keys on **client-generated UUIDs**,
carried as a new column on `checklist_sections`, `checklist_questions` and `checklist_options`. This
is what makes the save idempotent: the same payload sent twice produces the same tree, so a retry
after a timeout is free.

Rows present in the payload are upserted; rows absent from it are deleted. The payload is therefore
authoritative, which is safe only because the endpoint refuses anything but a bozza.

`position` is client-supplied on every node, not inferred from array order. Cross-section drag is
expressed as a change to a question's parent section plus its position, inside the same payload.

The existing granular endpoints (`POST .../sections`, `PATCH .../questions/{question}`, the
deletes) remain. They are not deprecated. Both write paths share one validator and one bozza guard;
neither gets its own copy of the invariants.

### Sharing freezes the checklist (ADR-0005)

A new `POST /checklists/{checklist}/share` takes the recipient list and an optional due date, and in
one `DB::transaction()` flips the status to published, stamps `published_at`, and writes one
assignment per recipient. Publishing without assigning is not a reachable state.

Every structural write endpoint — the reconciler and the granular ones — returns **409** for a
published checklist. 409 rather than 403: the caller is permitted, the resource is not in a state
that accepts the write.

Because nothing mutates after sharing, answers reference the question row directly. No snapshot
columns on the answer, and no soft deletes on sections, questions or options: those tables are hard
deleted, and that only ever happens to a bozza. This reverses an earlier position in the grilling
session, which had bought snapshotting to survive post-publish edits; freezing removes the need.

Re-sharing an already published checklist adds assignments without touching the structure.

### Schema changes

Added:

- `uuid` on `checklist_sections`, `checklist_questions`, `checklist_options` — the reconciler's key,
  unique per parent checklist.
- `checklist_questions.image_path` — an image the **author** pins to the question. Distinct from
  `allows_attachment`, which is about the **filler** uploading evidence; the grilling session
  conflated the two and they are separate columns for separate actors.
- `checklist_questions.allows_note` — renders a free-text box under the options for the filler.
  This is the prototype's *spazio note*: it appears greyed and undraggable among the options, which
  is what identifies it as a per-question flag rather than an option row.
- `checklist_answers.note_text` — where that note lands.
- `AssignmentStatus::Cancelled`.

Widened to `text`, and sanitised server-side on write to `<b> <i> <u> <br>` only:
`checklist_sections.title`, `checklist_questions.label`. No other authored field accepts markup;
option labels bolded inside a radio row are styling, not content.

Removed:

- `checklists.frequency` — checklists are one-shot; the DDL shares one whenever they want.
- `checklist_options.is_non_conformity` — answers are free, nothing is scored or failed.
- `checklist_submissions.export_pdf_path` — the PDF is the blank questionnaire, rendered on demand,
  never stored.
- `QuestionType::Image` — an image on a question is decoration on that question, not a question
  type. `QuestionType::Number` — covered by `Text`.
- `checklist_assignments.assignee_type` and `assignee_id`, replaced by a plain `assignee_user_id`
  foreign key. Org-role assignment is dropped: the DDL picks named people by hand. A role-targeted
  assignment cannot answer "who still owes me this?" once the role's membership changes.

Also to settle: `checklists.due_at` now duplicates `checklist_assignments.due_at`. With sharing as
the only publish path, the per-assignment date is the real deadline and the checklist-level column
should go.

Fixed: `checklist_submissions.status` has a column default of `'draft'`, a value `SubmissionStatus`
cannot cast — any row created without an explicit status throws on read. The default becomes
`in_progress`. There is no production data; staging is rebuilt with `migrate:fresh --seed`.

### Compilazione is atomic

There is no partial save. The filler's wizard holds answers client-side across sections and posts
them whole at submit. `POST /assignments/{assignment}/start` stays, as telemetry only: it stamps
`started_at` and moves the assignment to in_progress so the DDL's oversight view can show who has
begun. The unique constraint on `checklist_submissions.checklist_assignment_id` continues to
enforce one compilazione per assegnazione at the database level.

The exit confirm the designer describes belongs to the **editor**, not the fill wizard. The filler
gets no unsaved-changes dialog and no resume; the fill view's only terminal action is finalise.

### Access

Two lists, two authorizations, deliberately not one endpoint that returns a different dataset per
caller:

- `GET /companies/{company}/checklists` — the author's list, every checklist in the company, bozze
  included. Non-authors get 403.
- `GET /my/checklists` — the worker's list, assignments only.

Checklists do **not** join the document tree. There is no category or folder row, no nesting, no
moving; the "categoria" in the designer's notes is the checklist screen itself. This keeps one
permission rule per question — `EffectiveAccess` grants govern documents, assignment governs
checklists — rather than two resolvers that will eventually disagree about the same row.

Authoring stays gated on `CompanyPolicy`'s admin check, unchanged, with a `ponytail:` marker
recording the open question: a datore di lavoro who holds the DDL org role but is not the workspace
admin currently cannot author the thing this feature is named after. Widening it is a change to one
policy method, driven by `User::activeOrgRoleIds()`, with no schema consequence — deliberately
deferred rather than guessed.

`ChecklistPolicy::before()` keeps the operator bypass, subject to ADR-0003's client-status gate on
writes.

### PDF

`GET /checklists/{checklist}/pdf` renders the published questionnaire on demand and streams it:
sections, questions, options, and the images attached to either. Nothing is stored, so nothing goes
stale. Available to anyone who can see the checklist, authors and assignees alike, though the
current app surfaces the action only on the author's side.

This adds one dependency, `barryvdh/laravel-dompdf`; the project has no PDF library today.

### Duplication

`POST /checklists/{checklist}/duplicate` deep-clones a checklist — sections, questions, options and
their images — into a new bozza owned by the caller. This is the whole of the "start from a
template" requirement; the prototype's "template checklist" row is a checklist somebody named that,
not an entity.

`POST /checklists/questions/{question}/duplicate` clones one question in place, in the same section,
at the following position. Done server-side rather than client-side so that a question's images are
not re-uploaded.

### Uploads

Images on questions and options go through the existing sniffed-MIME allowlist in
`UploadedDocument::rules()` — the same allowlist, no SVG — and are served as attachments with
nosniff, like every other download in the platform. They are plain stored paths, not `File` rows:
document infrastructure carries folders, versions and access grants that a builder image has no use
for.

## Testing Decisions

A good test here asserts what a caller of the API can observe: the status code, the response body,
and what a subsequent request returns. It does not assert which rows moved, which service was
called, or how the reconciler decided. A test that would fail if the reconciler were rewritten to
reach the same outcome is testing the implementation and should be rewritten.

**One seam: `ChecklistApiTest`.** Every behaviour in this feature reaches the HTTP API, so nothing
below it needs a seam of its own. The reconciler in particular is exercised through
`PUT /checklists/{checklist}/structure` and gets no unit test — it has no meaning apart from the
endpoint it serves. `ChecklistApiTest` already covers the slice in this style and is the prior art:
Sanctum-authenticated `postJson`/`getJson`, assertions on status and JSON shape, no mocks.

Extend it with:

- A whole-tree save round-trips: the structure that comes back from `GET` matches what went in,
  including positions and nesting.
- The same payload sent twice produces one tree, not two — the idempotency claim that justifies
  client UUIDs.
- A payload that omits a question deletes it.
- A question moved to another section in the payload comes back under that section.
- Sharing publishes and assigns in one call, and a failure in either half rolls both back.
- A published checklist rejects every structural write with 409, including the granular endpoints.
- Duplicating a shared checklist yields an editable bozza with the same content and a new id.
- Duplicating a question places the copy in the same section, immediately after the original.
- A required question left unanswered blocks the submit and names itself in the error.
- Notes and attachments land on the answer when allowed, and are rejected when not.
- The author list and `/my/checklists` return different sets for the same shared checklist.
- `GET /checklists/{checklist}/pdf` returns a PDF content type for an author and for an assignee.
- Withdrawing an assegnazione cancels it and leaves an existing compilazione readable.

Delete `test_org_role_assignment_appears_in_my_checklists` — the behaviour it covers is being
removed, not broken.

**Second seam: `SecurityRegressionTest`**, one test per hole, as that suite's convention requires:

- A checklist id from another tenant returns 404, not 403, through the nested assignment routes —
  the `scopeBindings` rule that has already caused bugs elsewhere in this codebase.
- A non-author member calling the company checklist list gets 403 rather than a filtered list.
- An assignee cannot submit somebody else's assegnazione.
- A member whose membership is archived loses access to a checklist they were assigned.
- An upload whose sniffed MIME type is outside the allowlist is rejected on question and option
  images.

No new test file. `AccessControlTest` is not touched: checklists do not participate in the
hierarchical grant model, and adding them there would imply they do.

## Out of Scope

- **Recurring checklists.** No schedule, no next-occurrence, no reminders. Dropped with `frequency`.
- **Scoring, non-conformity, pass/fail.** Answers are free. A checklist that flags a failing answer
  is a different feature and would reintroduce `is_non_conformity`.
- **Editing a shared checklist.** Frozen by ADR-0005; duplication is the path.
- **Partial compilazione and resume.** One sitting, one submit.
- **Checklists in the document tree.** No category, no folder, no subfolders, no grants.
- **Templates as an entity.** Deep duplication covers it.
- **Org-role assignment.** Named people only.
- **Stored or exported PDFs of a compilazione.** The PDF is the blank questionnaire.
- **Notifications on share.** The platform has a `Notifica` concept; wiring checklist sharing into
  it is a separate piece of work.
- **Authoring gated on org roles.** Deferred deliberately; see Implementation Decisions.
- **Any Flutter work.** This spec is the backend contract only.

## Further Notes

`docs/er-model.md` §2.4 still describes the pre-change slice — `frequency`, `is_non_conformity`,
org-role assignees, `export_pdf_path`. It needs updating alongside the migrations, or it becomes the
stale reference that the next person trusts. `docs/er-model.html` is generated for the client from
the same material and should follow.

The screens this was modelled from are in `docs/app/xd/`. They cover the editor and the fill wizard
thoroughly; they do **not** show the sharing step, the recipient picker, the oversight view, or a
file-upload control in the fill view. Those four are inferred from the designer's notes and from the
existing endpoints, and are the likeliest places for this spec to be wrong.

The screenshots show a list row reading "aggiunto da edilcostruzioni" — a company name where the
model stores an authoring user. Treated as a display decision, derived from the author's company; no
column added.
