# A Prometeo Operator writes only in a client company's archive

Every policy's `before()` returns `true` for `UserType::PrometeoOperator`, which makes the operator
a super admin over every workspace. The flowchart draws a sharper line: an *azienda cliente
Prometeo* has its archive managed cooperatively — the operator uploads documents, sets deadlines,
registers the org chart — while an *azienda non cliente* "gestisce tutto in autonomia" and gets
help only through chat, mail or a call. So operator **writes** are gated by
`companies.is_prometeo_client`; **reads** stay open everywhere, because help desk is a duty owed to
every company that downloads the app.

Without the gate, a company that never bought anything from Prometeo can have its safety documents
edited by Prometeo staff. Legally that archive is the employer's; a silent third-party write in it
is a tenancy hole wearing a super-admin hat.

## Consequences

- One `before()` reading one predicate: `true` for read abilities, `true` for writes when the
  company is a client, `false` otherwise. The nine policies stay as they are — the same reason
  `EffectiveAccess` and `User::activeOrgRoleIds()` exist in one place each.
- `is_prometeo_client` is a stored attribute, not a derivation of `created_by_operator_id`. That
  column records a past event; client status is a commercial relationship that starts and ends
  independently, so a self-registered company that later buys consulting becomes a client without
  rewriting history. It defaults to `true` when an operator registers the company, and only an
  operator can change it, through an audited endpoint. A former client is `false` with
  `client_since` still set.
- **Every** cross-tenant operator action is written to `audit_logs`, reads included. The flag
  decides whether they may change something, not whether the visit is recorded.
- A document a person marked private in their area riservata is denied to the operator too, client
  company or not. "Solo io" cannot mean "except the vendor": if the employer cannot see it, neither
  can Prometeo. Help desk gets file metadata and the logs, never the content.
