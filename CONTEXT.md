# Prometeo

Workplace-safety (D.Lgs 81/08) document and compliance platform: companies keep the documents,
appointments, training records and checklists the law requires, and Prometeo's own staff assist the
companies that buy its consulting services. This file is the glossary — the shared language of the
domain, nothing else. Entities, fields and rules live in `docs/er-model.md`.

## Language

### Tenancy and identity

**Workspace**:
The tenant every document, membership and subscription belongs to. Exists in two kinds — a
*business workspace* for a company, a *personal workspace* for a worker with no company.
_Avoid_: tenant, account, organization

**Azienda**:
A company that uses the platform, headed by its Datore di Lavoro. Always a business workspace.
_Avoid_: organization, org, client (a company is not necessarily a client — see below)

**Azienda cliente Prometeo**:
A company that buys Prometeo's consulting and training services, so Prometeo staff cooperate on
its archive. A commercial relationship that can begin and end at any time, independent of who
first registered the company.
_Avoid_: paying customer, subscriber (a subscription is a plan, not a consulting contract)

**Azienda non cliente**:
A company that uses the app to manage its own safety documents autonomously, with no consulting
relationship. Prometeo staff may assist it but never write in its archive.
_Avoid_: free user, trial company

**Prometeo Operator**:
Prometeo staff. Works across every workspace, holds no membership, registers and assists client
companies and authors the preset structures every workspace starts from.
_Avoid_: admin, super admin, backoffice user

**Lavoratore singolo**:
A worker who joined no company and keeps their work and training documents in a personal
workspace of their own.
_Avoid_: freelancer, individual user, unassociated worker

**Membership**:
The relationship between a person and a workspace. It carries the person's standing in that
workspace — job title, department, active or archived — and every permission they hold there.
A role is a property of the membership, never of the person.
_Avoid_: user role, seat

### The org chart

**Figura aziendale**:
A position in a company's D.Lgs 81/08 org chart — Datore di Lavoro, Dirigente, RSPP, ASPP, Medico
Competente, Preposto, RLS/RLST, Lavoratore. Held through a membership, and it may require an
appointment document.
_Avoid_: permission group, user type

**Datore di Lavoro (DDL)**:
The employer: the first person to register a company, the one who invites every other figure and
decides what each of them may see and change. One per company.
_Avoid_: owner, company admin

**Secondo Datore di Lavoro**:
A second employer registered by the DDL to share the work of running the company's archive. Same
capabilities as the DDL.
_Avoid_: co-admin, delegate

**Figura interna / esterna**:
Whether a figure is an employee of the company or an outside professional serving it under
contract. An external figure — typically an RSPP, ASPP, Medico Competente or RLST — holds a
membership in every company that appointed them and switches between their archives.
_Avoid_: consultant flag, contractor

**Mansione**:
A person's job title inside one company, as the DDL writes it. Groups workers in the training
overview (addetti primo soccorso, addetti antincendio, …); it is not an org-chart figure.
_Avoid_: role, position, qualification

**Guest / Ospite**:
An outside person — often from a subcontractor — invited by the DDL to a named set of categories
or folders for a limited time, with no place in the org chart and no access to anything else.
_Avoid_: external user, collaborator, shared user

### The archive

**Categoria**:
The top level of a workspace's archive, holding folders. Its name and icon are the company's to
change.
_Avoid_: section, group

**Cartella**:
A container of documents inside a category, nestable.
_Avoid_: directory

**Documento**:
A file in the archive, kept as an ordered series of versions so that every replacement stays
traceable. Carries the dates the law cares about: when it was issued, when it expires.
_Avoid_: attachment, upload

**Preset**:
A category or folder structure authored by a Prometeo Operator, copied into a workspace when it is
created. From that moment the copy is the workspace's own: renaming or archiving it changes nothing
anywhere else.
_Avoid_: default folder, global category, shared template

**Area riservata**:
The branch of the archive belonging to one person, where they keep their personal documents —
certificates, attestati — inside the company's workspace.
_Avoid_: private folder, my documents

**Documento privato**:
A document in someone's area riservata that they chose to keep to themselves. "Solo io" is
absolute: not the DDL, not a Prometeo Operator.
_Avoid_: hidden file, restricted document

**Condivisione**:
A permission the DDL grants over a category, folder or document, to a person or to an org-chart
figure, optionally until a date. Three levels: *Visualizzatore* reads, *Custode* also updates
metadata and replaces versions, *Editor* also deletes.
_Avoid_: ACL, share, permission grant

**Presa visione**:
A person's confirmation that they have read a specific version of a document the company made
mandatory.
_Avoid_: signature, acceptance, read receipt

**Scadenza**:
The date a document stops satisfying its legal obligation. Derived from the document's issue date
and its type's validity period, unless someone sets it by hand.
_Avoid_: due date, deadline

### Monitoring

**Checklist**:
A form the company fills in periodically to record a safety check. Assigned to figures or people,
answered as a submission.
_Avoid_: form, survey, questionnaire

**Near miss**:
An event that could have caused harm and did not, reported so the company can act on it.
Reportable anonymously.
_Avoid_: incident, close call

**Infortunio**:
An injury at work, recorded with its absence days, which decide whether it counts as serious.
_Avoid_: accident, event

**Monitoraggio formazione**:
The DDL's overview of who is trained in what, grouped by mansione, built from the workers'
training certificates and their expiry dates.
_Avoid_: training matrix, compliance dashboard

### Assistance and notifications

**Assistenza tecnica**:
A conversation with a Prometeo Operator about the app itself — access, uploads, something not
working.
_Avoid_: support ticket, helpdesk request

**Assistenza commerciale**:
A conversation with a Prometeo Operator about plans, subscriptions and services.
_Avoid_: sales chat, billing support

**Notifica**:
A record of something the platform needed to tell one person — a document uploaded, a deadline
approaching, a checklist to fill in. It is delivered to their device and by mail, and it stays in
their history whatever they do with the copy on their phone.
_Avoid_: alert, push, message
