# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the
codebase. This repo is **single-context**: one `CONTEXT.md` at the root, one `docs/adr/`.

## Before exploring, read these

- **`CONTEXT.md`** at the repo root. It is a glossary and nothing else — no schema, no endpoints,
  no implementation detail. Read it before naming a new concept.
- **`docs/adr/`**: read the ADRs that touch the area you are about to work in.
- **`docs/er-model.md`**: the full entity and field reference, plus open questions. Read it before
  changing schema or adding a domain concept. It is a reference document, not a decision record —
  when it disagrees with an ADR, the ADR wins and the er-model is stale.

If any of these files don't exist, **proceed silently**. Don't flag their absence; don't suggest
creating them upfront. The `/domain-modeling` skill (reached via `/grill-with-docs` and
`/improve-codebase-architecture`) creates them lazily when terms or decisions actually get resolved.

## File structure

```
/
├── CONTEXT.md          ← glossary
├── docs/
│   ├── adr/            ← decisions that outlive their feature
│   ├── specs/          ← work not yet done (see issue-tracker.md)
│   ├── er-model.md     ← entity/field reference
│   └── er-model.html   ← client-facing diagrams
└── app/
```

## Use the glossary's vocabulary

When your output names a domain concept — a spec title, an issue, a hypothesis, a test name, a
class or column name — use the term as defined in `CONTEXT.md`, and respect its `_Avoid_` list.
The glossary deliberately keeps Italian domain terms (`bozza`, `condivisione`, `assegnazione`,
`mansione`, `infortunio`) where the spec and the prototypes use them; do not translate them into
English synonyms the glossary avoids.

Note the project's language rule in `CLAUDE.md`: code, comments and commits are English, but domain
terms and user-facing strings from the spec stay Italian. A glossary term is a domain term.

If the concept you need isn't in the glossary yet, that's a signal: either you're inventing language
the project doesn't use (reconsider) or there's a real gap (note it for `/domain-modeling`).

## Flag ADR conflicts

If your output contradicts an existing ADR, surface it explicitly rather than silently overriding:

> _Contradicts ADR-0005 (sharing a checklist freezes it), but worth reopening because…_
