# Deployment (Laravel Forge → DigitalOcean)

Staging is the only environment for now. Production is a promotion of this
setup — see the checklist at the bottom.

## Ownership

| Thing | Owner |
|---|---|
| DigitalOcean account | Prometeo Srl (client) |
| Laravel Forge account | Prometeo Srl (client) |
| Git remote Forge clones | `client` → `https://github.com/Prometeo-Srl/arkistapp-backend.git` |
| Deployed branch | `develop` |

Forge deploys from **one** remote. Pushes to `origin`
(`algomeraIT/prometeo-backend`) do **not** deploy — every change meant for
staging must also reach `client`.

> Known blocker: pushing `develop` to `client` over HTTPS fails unless the
> GitHub PAT carries the `workflow` scope (the branch contains
> `.github/workflows/`). Either regenerate the PAT with `workflow`, or switch
> the remote to SSH: `git remote set-url client git@github.com:Prometeo-Srl/arkistapp-backend.git`.

## Server

App Server (nginx + PHP-FPM + PostgreSQL + Redis on one droplet).

| Setting | Value | Why it is fixed |
|---|---|---|
| PHP | 8.3+ (8.4 fine) | `composer.json` requires `^8.3` |
| PostgreSQL | **18** | matches `compose.yaml` (`postgres:18-alpine`); Forge cannot change the major version after provisioning |
| Redis | yes | cache + queue |
| Size | 2 vCPU / 4 GB | |
| Region | near the users (Frankfurt / Amsterdam) | |
| Website isolation | **on** | SSH user becomes the site user, not `forge` |

Domain: the Forge-hosted `*.on-forge.com` subdomain gives free SSL
immediately. A real host (e.g. `api.arkistapp.it`) needs an A record from the
domain owner first.

## Deploy script

No frontend build exists in this repo (no `package.json`), so there are no
npm steps. Forge's generated script omits `composer install` — it is added
here.

```bash
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force
$ACTIVATE_RELEASE()
$RESTART_QUEUES()
```

`db:seed --force` is safe on every deploy: `OrgRoleSeeder`,
`DocumentTypeSeeder` and `PlanSeeder` are all `updateOrCreate`, and
`DatabaseSeeder` skips the `test@example.com` operator when
`app()->isProduction()`.

`migrate --force` runs **before** `$ACTIVATE_RELEASE()`: the schema changes
while the previous release still serves traffic. **Migrations must be
additive.** Never drop or rename a column in the same deploy that stops
writing to it — split it across two deploys. There is no rollback beyond a
database restore.

## Environment

Forge → Environment is the only copy of these values. Back them up somewhere
the client can reach.

```dotenv
APP_NAME=Prometeo
APP_ENV=production          # not "staging": DatabaseSeeder gates the operator account on isProduction()
APP_KEY=                    # Forge generates one; keep it, rotating it invalidates encrypted data
APP_DEBUG=false
APP_URL=https://<the actual host>   # wrong value silently breaks links in mail

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=<forge-created db>
DB_USERNAME=forge
DB_PASSWORD=<from Forge>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

BROADCAST_CONNECTION=log    # no Reverb in this app
FILESYSTEM_DISK=local       # document uploads live in shared storage/, see below

MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=api
MAIL_PASSWORD=<Mailtrap API token>
MAIL_FROM_ADDRESS=no-reply@arkistapp.it
MAIL_FROM_NAME="Prometeo"
```

`APP_ENV=production` on staging is deliberate: the demo operator account is
gated on it, and a public host must not mint a cross-tenant super admin with a
factory password.

**`artisan optimize` caches config on every deploy.** Editing `.env` in Forge
changes nothing until a redeploy or a manual `artisan optimize`. No error, no
signal — this is the most common "I changed it and nothing happened".

Mail: the sandbox Mailtrap host in `.env.example` swallows mail. Staging needs
the **live** Mailtrap sending host (or Resend, already installed as a
transport) plus a verified sending domain, otherwise the email-verification
code never arrives.

## Daemons and scheduler

| Process | Where | Without it |
|---|---|---|
| Queue worker (`artisan queue:work redis --sleep=3 --tries=3`) | Forge → Daemons | nothing queued runs — **silently** |
| Scheduler (`artisan schedule:run`, every minute) | Forge → Scheduler | anything in `routes/console.php` stops |

Nothing implements `ShouldQueue` today, so the worker changes no behaviour
yet — set it up anyway, since the first queued notification will otherwise
fail with no user-visible error.

## Files not in git

Document uploads land on the `local` disk under `storage/app`. Forge's
`$CREATE_RELEASE()` links `storage/` from the shared site directory, so
uploads survive deploys. If any runtime secret file is ever added (a
service-account JSON, a signing key), upload it into **shared** storage, not a
release directory, and verify:

```bash
readlink -f current/storage/app/private/<the-file>
# good: <site>/storage/...   bad: <site>/releases/<n>/...
```

Mode 600. A fresh server always needs its own upload.

**Destructive in Forge: re-running "Install Repository" wipes untracked files
in the site directory**, including anything uploaded by hand. Changing the
branch, `.env` or the domain is not destructive.

## Verify after a deploy

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/up   # expect 200
ssh <site-user>@<ip>          # site user, not `forge` — isolation is on
cd ~/<site>/current
php artisan about
php artisan migrate:status | tail
php artisan queue:monitor
```

Forge's Commands tab can swallow output under website isolation. Use SSH when
output matters.

Also check that `/telescope` is **404** on the server: Telescope is a
`require-dev` package and is registered only when `APP_ENV=local`
(`AppServiceProvider::register()`). If it ever 200s in production, the
registration gate has been broken.

## Runbook

| Symptom | Check first |
|---|---|
| Env change had no effect | config cache — redeploy or `artisan optimize` |
| App 500s right after a deploy | a dev-only package registered outside `local` (Telescope, Boost, Pail) |
| No verification mail | queue worker alive? then the Mailtrap host — sandbox vs live |
| Flutter app cannot reach the API | `APP_URL` + the client's `env_config.dart` baseUrl |
| Migration failed mid-deploy | previous release still serving; fix forward, there is no rollback |

## Promotion to production

| # | Where | If missed |
|---|---|---|
| 1 | `APP_URL` | wrong links in mail |
| 2 | Forge site domain + SSL (real domain, A record) | cert mismatch |
| 3 | Mail: live sending domain + `MAIL_FROM_ADDRESS` | verification mail lands in spam or bounces |
| 4 | Flutter client `env_config.dart` baseUrl | app talks to the old host |
| 5 | Forge deploy branch → `main` | staging code in production |

Also at promotion: create the first `PrometeoOperator` by hand (the seeder
does not create one under `APP_ENV=production`), and agree in writing whether
staging data is wiped.
