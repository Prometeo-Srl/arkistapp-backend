# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Prometeo: JSON API backend (Laravel 13, PHP 8.3+, PostgreSQL 18) for a multi-tenant
workplace-safety (D.Lgs 81/08) platform. The consumer is a **single Flutter app** handling every
role and flow (operator, company, worker) — not one app per role. No Blade UI, no frontend build:
every endpoint lives under `/api`.

Domain vocabulary: `CONTEXT.md` (glossary — read it before naming a new concept; decisions in `docs/adr/`).
Domain reference: `docs/er-model.md` (full entity/field reference + open questions),
`docs/er-model.html` (client-facing diagrams), `docs/Prometeo.pdf` (original spec, read-only source).
Read `docs/er-model.md` before changing schema or adding a domain concept.

## Commands

Sail is the environment (`compose.yaml`: `laravel.test`, `pgsql`, `redis`). Check
`docker compose ps` before `./vendor/bin/sail up -d`.

```bash
./vendor/bin/sail artisan test                              # full suite
./vendor/bin/sail artisan test --filter=AccessControlTest    # one test class
./vendor/bin/sail artisan test --filter=test_method_name     # one test
./vendor/bin/sail artisan test tests/Feature/DocumentApiTest.php
./vendor/bin/sail artisan migrate:fresh --seed               # reset schema + reference data
./vendor/bin/sail composer run test                          # config:clear then test
./vendor/bin/sail pint                                       # code style (run before committing)
./vendor/bin/sail artisan route:list --path=api
```

Never run bare `php artisan migrate` — the `pgsql` host only resolves inside the Sail network.
Tests use `RefreshDatabase` against the `testing` Postgres database created by Sail's init script.

Telescope is installed (dev): `/telescope`, disabled during tests.

## Architecture

### Tenancy is a workspace, not a company

`Company` is the tenant, with `kind` ∈ {`business`, `personal`}. An unassociated worker gets a
**personal workspace** — a real `Company` row with a membership — so archives, subscriptions and
permissions follow one code path. It is created only by `POST /api/companies/personal`
(idempotent: 201 first time, 200 after); a DB constraint enforces one per owner. `GET /companies`
must stay a pure read.

All access derives from `CompanyMembership` (`status`, `is_admin`) plus `MembershipRole` →
`OrgRole` (the D.Lgs 81/08 org chart: RSPP, medico competente, preposto…). Archiving a membership
must end every derived permission — that is why `User::activeOrgRoleIds()`, `activeCompanyIds()`,
`isMemberOf()`, `isAdminOf()` all filter on `MembershipStatus::Active`. Use those methods; do not
hand-roll membership queries in controllers.

### Authorization

Policies + `$this->authorize()` are the convention (`CompanyPolicy` is the umbrella for the whole
tenancy slice: members, invitations, subscriptions all authorize against the company).
`before()` returns true for `UserType::PrometeoOperator` (cross-tenant super admin, holds no
membership).

Document permissions are hierarchical and resolved by `App\Support\EffectiveAccess`: grants on a
category cascade to folders and files; strongest grant wins (`AccessPermission::rank()`).
Personal-branch ownership is read from folder ancestors (`ownsPersonalBranch`), because sub-folders
do not repeat `is_personal_of_user_id`.

### Routes

`routes/api.php` is pure wiring: `require` of eight slice files in `routes/slices/`
(auth, catalog, tenancy, documents, checklists, incidents, support, monitor). Add endpoints to the
matching slice. Two rules that have already caused bugs:

- Nested resources must sit inside `Route::scopeBindings()` so `{membership}`/`{invitation}` resolve
  through the parent `{company}` — otherwise an id from another tenant is a cross-tenant hijack
  instead of a 404.
- Literal segments before parameters: `/companies/personal` is declared before `/companies/{company}`.

Rate limiters live in `AppServiceProvider::configureRateLimiting()` (`login`, `invitations`, `api`) —
Laravel 11+ throttles nothing by default.

### Conventions

- Request validation in `App\Http\Requests\*` (Form Requests), responses via `App\Http\Resources\*`
  API Resources. Controllers stay thin.
- Domain vocabulary is enums in `App\Enums\*` — never hardcode status strings.
- Polymorphic columns use the morph map in `AppServiceProvider::boot()` (`user`, `company`,
  `category`, `folder`, `file`, …). Add new morphable models there; the DB must never store FQCNs.
- Uploads validate the **sniffed MIME type** against the single allowlist in
  `App\Support\UploadedDocument::rules()` (no SVG). Downloads are served as attachments with nosniff.
- Models use PHP attributes (`#[Fillable]`, `#[Hidden]`) rather than `$fillable` properties.
- Multi-row writes (file + first version) go in `DB::transaction()`.
- List endpoints paginate.

### Testing

Feature tests are per slice (`TenancyApiTest`, `DocumentApiTest`, `ChecklistApiTest`, …) plus two
cross-cutting suites that must keep passing: `SecurityRegressionTest` (one test per fixed
vulnerability — cross-tenant binding, duplicate submissions, MIME payloads) and `AccessControlTest`
(hierarchical grant resolution). New authorization or tenancy work belongs in those.

## Client repo (local machine only)

The consuming Flutter app is a separate repo, **not committed here and not available in CI or on
any other machine**. On this dev box it lives at:

```
/home/nagonere/Scrivania/dev/flutter/algomera/prometeo-flutter/
```

It has its own `CLAUDE.md`. One package (`prometeo`) serving all roles and flows — company and
worker UI both live there, role-gated at runtime. Clean Architecture, `flutter_bloc`, Dio,
Bearer-token auth. Useful when a change touches the API contract: check
`lib/features/*/data/models/` and `lib/core/utils/env_config.dart` there to see what the client
actually sends and parses before renaming a field or changing a response shape. Its dev `baseUrl`
points at `10.0.2.2` (Android-emulator loopback to this host), so Sail must be up for the app to
reach the API.

## Language

Code, comments and commits in English. Domain terms and user-facing strings from the spec/prototypes
stay Italian (Monitora attività, Cambio profilo, Custode/Editor/Visualizzatore).

## Agent skills

### Issue tracker

Specs and issues are committed markdown under `docs/specs/`, numbered like the ADRs. There is no
`gh` CLI on this machine, so GitHub Issues is not a surface any skill should write to. See
`docs/agents/issue-tracker.md`.

### Domain docs

Single-context: `CONTEXT.md` (glossary) and `docs/adr/` at the root, with `docs/er-model.md` as the
entity reference. See `docs/agents/domain.md`.
