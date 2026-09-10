# Preset structures are separate template tables, cloned on workspace creation

Prometeo Operators author the categories and folders every workspace starts from — one set for
business workspaces, a smaller one for personal ones — and each workspace may then rename, re-icon
or archive its copy freely. We store these in dedicated `category_templates` and `folder_templates`
tables, tagged by target `WorkspaceKind`, and copy the matching set when a workspace is created.

The alternative was to keep presets in `categories`/`folders` with a NULL `company_id`. That puts
untenanted rows inside the tables the global tenant scope filters by `company_id`: every existing
listing would either surface templates as if they were the company's own or need a `whereNotNull`,
and one forgotten `whereNotNull` is a cross-tenant bug. Separate tables cost one migration and
stay outside the scope entirely.

## Consequences

- The seeders that today hand a workspace its starting tree (`PersonalFoldersSeeder`) become
  template seeds; `Company::personalFor()` and business-company creation both clone, so there is
  one implementation of "give this workspace its starting structure" rather than two.
- **No backfill.** Editing a template later does not touch existing workspaces. A company that has
  already reorganised and renamed its tree would otherwise have folders injected into it, and there
  is no sensible answer for a category the company deliberately archived. If the client ever needs
  an operator to push a new preset to existing workspaces, that is a job, not a schema change.
