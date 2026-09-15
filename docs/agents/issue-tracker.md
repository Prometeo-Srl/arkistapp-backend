# Issue tracker: Local Markdown, committed

Issues and specs for this repo live as markdown files under `docs/specs/`, tracked in git and
pushed like any other file. They are not in GitHub Issues: the `gh` CLI is deliberately absent from
this machine, so no skill should attempt `gh issue create`, `gh issue list`, or any other GitHub
API call. The repo has two GitHub remotes (`origin` → `algomeraIT/prometeo-backend`, `client` →
`Prometeo-Srl/arkistapp-backend`); neither is an issue surface for these skills.

## Conventions

- One feature per file: `docs/specs/<NNNN>-<feature-slug>.md`, numbered from `0001`, mirroring the
  `docs/adr/` convention already in use. Scan the directory for the highest number and increment.
- A feature that grows past one file becomes a directory: `docs/specs/<NNNN>-<feature-slug>/`, with
  `spec.md` at its root and implementation issues as one file per ticket under
  `issues/<NN>-<slug>.md`, numbered from `01`. Never a single combined tickets file.
- Triage state is a `Status:` line near the top of each issue file. The `triage` skill is not
  installed in this environment and there is no `triage-labels.md`; until it is, use plain
  `Status: open` / `Status: done` rather than inventing a label vocabulary.
- Comments and conversation history append to the bottom under a `## Comments` heading.

## When a skill says "publish to the issue tracker"

Write the file under `docs/specs/`, creating the directory if needed. Do not open a GitHub issue.
Do not apply a triage label — there is no label vocabulary configured for this repo.

## When a skill says "fetch the relevant ticket"

Read the file at the referenced path. The user will normally pass the path or the spec number.

## Relationship to the other docs

`docs/specs/` is for work that has not happened yet: the problem, the solution, the stories, the
decisions taken while planning it. `docs/adr/` is for decisions that are hard to reverse and need
to outlive the feature that prompted them. `CONTEXT.md` is the glossary and holds neither.

A spec may reference an ADR; it must not restate it.

## Wayfinding operations

Used by `/wayfinder`. The **map** is a file with one **child** file per ticket.

- **Map**: `docs/specs/<NNNN>-<effort>/map.md` (the Notes / Decisions-so-far / Fog body).
- **Child ticket**: `docs/specs/<NNNN>-<effort>/issues/NN-<slug>.md`, numbered from `01`, with the
  question in the body. A `Type:` line records the ticket type (`research`/`prototype`/`grilling`/
  `task`); a `Status:` line records `claimed`/`resolved`.
- **Blocking**: a `Blocked by: NN, NN` line near the top. A ticket is unblocked when every file it
  lists is `resolved`.
- **Frontier**: scan the `issues/` directory for files that are open, unblocked, and unclaimed;
  first by number wins.
- **Claim**: set `Status: claimed` and save before any work.
- **Resolve**: append the answer under an `## Answer` heading, set `Status: resolved`, then append
  a context pointer to the map's Decisions-so-far in `map.md`.
