# Prometeo documentation

| File | Audience | Contents |
|---|---|---|
| `er-model.html` | Client, new developers | Illustrated ER model: one diagram per domain, key fields, rules and open questions. |
| `er-model.md` | Developers | Full reference: every entity with all its fields, cross-cutting constraints, implementation status. |
| `Prometeo.pdf` | — | Original technical specification (Allegato 1 – Piano delle Attività). A source: do not edit. |

`er-model.html` is the source of the published page, not a standalone file: its diagrams are
Mermaid blocks rendered by whoever hosts the page, so opening it straight from disk shows the
diagram source rather than the drawing. To read it the way the client sees it, use the published
version; to update a diagram, edit its text rather than an image. `er-model.md`, on the other
hand, reads fine as-is — GitHub and IDEs render its Mermaid blocks.

Published page: https://claude.ai/code/artifact/22853e10-2a2c-4d0d-a4b9-b54200ef661f

The model's other two sources are the XD prototypes:

- company — https://xd.adobe.com/view/3f0926e3-d024-4d99-b0b1-d67bfc5be0af-6a5a/grid
- worker — https://xd.adobe.com/view/488bdfc2-6a3e-4a6b-8210-f0786cdb3854-1dd8/grid
