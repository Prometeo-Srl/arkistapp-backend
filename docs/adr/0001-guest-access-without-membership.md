# A Guest holds access grants, never a membership

The flowchart adds the *Guest / Ospite*: someone outside the company — often a subcontractor on a
construction site — whom the DDL invites to a named set of categories or folders, for a limited
time, to read, upload or download and nothing else. The obvious modelling is a `CompanyMembership`
with a `guest` `OrgRole`, and we deliberately rejected it: a guest is represented **only** by
`AccessGrant` rows over the shared categories/folders, with `expires_at` as the single clock.

Every permission in this codebase derives from an active membership — `User::activeCompanyIds()`,
`activeOrgRoleIds()`, the Eloquent tenant scope, checklist assignment lookup, subscription
coverage. A guest membership would enrol an outside contractor into all of it at once, and the
work of keeping them out would be a negative condition repeated in every one of those queries,
each of which is a cross-tenant leak the day someone forgets it. Having no membership is not an
omission here; it is the mechanism.

## Consequences

- The invitation lives on the grant itself (`invite_token`, `invite_sent_at`, `accepted_at`), not
  in `invitations` — that table's `company_id` is non-nullable and accepting one creates a
  membership, which is precisely what must not happen. When the invited address already has an
  account, `grantee_id` is set at once and the mail is a plain notice with nothing to redeem.
- Grants always target a named person, never a company, even when the guest "is" a company: a
  company-wide grant would silently extend to its future hires.
- The guest list is a filter over `access_grants`, not a resource of its own — so revoking a share
  stays in one place.
- A guest who later subscribes becomes a Datore di Lavoro through the ordinary registration path.
  There is no conversion flow: the account is the same, and the grants on someone else's workspace
  are untouched by it.
