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
| Region | **Frankfurt (fra1)** | nearest DO region to Italy, and keeps personal data in the EU — this app stores health-surveillance records |
| Website isolation | **on** | SSH user becomes the site user, not `forge` |

Domain: the Forge-hosted `*.on-forge.com` subdomain gives free SSL
immediately. A real host (e.g. `api.arkistapp.it`) needs an A record from the
domain owner first.

### Droplet size

**Staging: Premium AMD, 2 vCPU / 4 GB / 80 GB NVMe.**
**Production: 4 vCPU / 8 GB / 160 GB**, with documents moved off the droplet
(next section).

The workload is not CPU-bound: no queued jobs exist (nothing implements
`ShouldQueue`), there is no frontend build, no websockets, and a single Flutter
client. What the box actually does is hold Postgres and serve file uploads and
downloads.

4 GB is the floor rather than a comfort margin, because the App Server recipe
puts Postgres 18 **and** Redis **and** PHP-FPM on the same box. Two of the
request paths are memory-hungry per request and do not shrink with tuning:

- `FileController::renderThumbnail()` loads the whole image into Imagick as a
  blob. A phone photo decompresses to far more than its file size.
- `FolderController` and `IncidentController` build ZIP archives of a whole
  folder tree on disk before sending them.

So size for concurrent large requests, not for request rate.

### Disk is the constraint, not CPU

`App\Support\UploadedDocument::MAX_KILOBYTES` is **1048576 KB — 1 GB per
file**, `FILESYSTEM_DISK=local`, and `FileVersion` retains every version of
every file. Storage only grows.

**A DigitalOcean droplet disk resize is one-way**: it can grow, it needs a
reboot, and it can never shrink. Do not treat the droplet disk as the long-term
home for documents.

Two decisions follow, and the second one is the scalable choice — see
"Moving documents to Spaces" below for why it is worth doing before the archive
grows.

### PHP extensions

Forge's PHP install does **not** include Imagick by default. The app requires
it — `FileController::renderThumbnail()` calls `new \Imagick` directly, so
thumbnail requests 500 without it.

Both extensions are now declared in `composer.json` (`ext-imagick`, `ext-zip`),
so `composer install` fails loudly at deploy time instead of the app failing at
runtime. On a fresh droplet, install them before the first deploy:

```bash
sudo apt-get install -y php8.4-imagick php8.4-zip
sudo service php8.4-fpm restart
```

### Upload limits

`MAX_KILOBYTES` allows 1 GB, but **Forge's nginx defaults to
`client_max_body_size 100m`** — a larger upload is rejected by nginx with a 413
before PHP or the validator ever sees it, so the API returns an error the
Flutter client does not expect.

Pick one and make the whole stack agree:

- **Recommended: lower the application limit.** No D.Lgs 81/08 document —
  DVR, certificate, medical record — is 1 GB. Set `MAX_KILOBYTES` to something
  honest (51200 = 50 MB) and raise nginx to match with headroom.
- Or keep 1 GB and raise every layer.

For a 50 MB ceiling, Forge → the site → Edit Files → nginx configuration:

```nginx
client_max_body_size 64m;
```

and Forge → PHP → php.ini (FPM):

```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
memory_limit = 512M
```

`memory_limit` is the one that matters for thumbnails and ZIP building, not the
upload itself — uploads stream to a temp file.

All four values must be at least the application limit, and nginx must be the
largest. A mismatch fails silently in a different way at each layer.

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

STRIPE_KEY=                 # test keys on staging, live keys on production
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=      # per environment — see the Stripe section
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

## Moving documents to Spaces (do this early)

The scalable home for documents is DigitalOcean Spaces (S3-compatible, ~$5 for
250 GB, in `fra1` alongside the droplet). `config/filesystems.php` already
defines an `s3` disk, so the configuration side is just env vars.

**Do it before the archive grows.** The migration cost is not the code — it is
copying live customer documents and reconciling `file_versions.storage_path`
against what actually landed in the bucket, with a correctness bar set by a
legal-archive product. That job is cheap at 50 files and expensive at 50,000.

It is **not** a matter of flipping `FILESYSTEM_DISK`. Two call sites use
`Storage::path()`, which does not exist on the S3 driver and throws:

- `app/Http/Controllers/FolderController.php:130` — folder ZIP download
- `app/Http/Controllers/IncidentController.php:164` — incident attachment ZIP

Both feed a local absolute path to `ZipArchive::addFile()`. Under S3 they must
stream the object instead (`Storage::readStream()` into a temp file, or
`$zip->addFromString(Storage::get(...))` for small attachments — the streaming
form matters here, given the file-size ceiling).

Full task list for whoever picks this up:

1. `composer require league/flysystem-aws-s3-v3`.
2. Fix the two `Storage::path()` call sites above.
3. Create the Space in `fra1`, keep it **private** — these are confidential
   records; downloads already go through `FileController` so the app can keep
   authorizing them and hand out temporary signed URLs.
4. Set `AWS_*` env vars (below), deploy, verify a fresh upload lands in the
   bucket.
5. Copy existing files, then verify every `file_versions.storage_path` and
   `incident_attachments.storage_path` resolves via `Storage::exists()` before
   deleting anything from the droplet.
6. Note that all disks are configured `'throw' => false`: a failed S3 write
   returns `false` rather than raising. Consider flipping the `s3` disk to
   `'throw' => true` so a storage outage is not silently a lost document.

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<Spaces key>
AWS_SECRET_ACCESS_KEY=<Spaces secret>
AWS_DEFAULT_REGION=fra1
AWS_BUCKET=<space name>
AWS_ENDPOINT=https://fra1.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

## Stripe

Subscriptions are live code (`App\Support\StripeGateway`,
`SubscriptionController`), and `routes/slices/stripe.php` exposes an
unauthenticated webhook — the Stripe signature header is what authenticates it,
which is why `STRIPE_WEBHOOK_SECRET` is not optional.

```dotenv
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
```

| Environment | Keys | Webhook endpoint to register in Stripe |
|---|---|---|
| Staging | **test** keys | `https://<staging host>/api/stripe/webhook` |
| Production | **live** keys | `https://<prod host>/api/stripe/webhook` |

Each environment needs its **own** webhook endpoint registered in the Stripe
dashboard, with its own signing secret. This fails silently and expensively: a
missing or stale webhook means Stripe charges the customer and the subscription
never activates in Prometeo, with no error anywhere in the app.

The Stripe live secret key is shown **once, at creation**. Store it in the
client's password manager at that moment. For team access, Stripe's
**Developer** role is enough for keys and webhooks.

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
| 6 | **Stripe: swap test keys for live** | payments run against the test ledger, no real money moves |
| 7 | **Stripe: register the production webhook + its own signing secret** | customer is charged, subscription never activates, no error anywhere |
| 8 | Spaces bucket + `AWS_*` for the new environment | documents written to a droplet disk that will fill |
| 9 | DO backups enabled on the production droplet | the documents *are* the product |

Items 6 and 7 fail silently. Verify them with a real test transaction, not by
reading the config.

Also at promotion: create the first `PrometeoOperator` by hand (the seeder
does not create one under `APP_ENV=production`), and agree in writing whether
staging data is wiped.

## Backups

Enable DO droplet backups (+20% of droplet cost, weekly snapshots). Weekly is
thin for a legal archive, so also schedule a Postgres dump — `pg_dump` to
Spaces on a Forge scheduled job is enough, and it is the only copy of the
tenancy and access-grant data that is not on the droplet.

Once documents live in Spaces, turn on bucket versioning there too: the app
retains `FileVersion` rows, but nothing protects against an accidental object
delete.

## Staying patched without a maintainer

The repo patches itself: Dependabot opens a PR the moment an advisory names a
package we depend on, `.github/workflows/dependabot-auto-merge.yml` waits for
the suite and merges it. That closes CVEs **in the code**. Three things sit
outside CI, and without them the fix never reaches the running server:

0. **Enable it on the `client` repo, not `origin`.** Forge deploys from
   `Prometeo-Srl/arkistapp-backend`; Dependabot security updates and Actions
   must be switched on *there* (Settings → Code security). Enabling them on
   `algomeraIT/prometeo-backend` hardens a repo nothing deploys from. Note the
   PAT `workflow` scope blocker at the top of this file applies to every change
   under `.github/` — including this automation itself.
1. **Forge auto-deploy must be on** for the site (Forge → Site → *Quick Deploy*).
   Otherwise a merged security fix sits in `develop` and production keeps
   serving the vulnerable version. This is the single most important switch on
   this page — everything upstream of it is wasted without it.
2. **Unattended security upgrades on the droplet.** Composer advisories say
   nothing about OpenSSL, nginx or the kernel. On the Ubuntu droplet:
   `apt install unattended-upgrades` and enable the security origin only.
3. **PHP and Postgres reach end of life.** `php8.5` and `postgres:18` stop
   receiving security patches on a published date; no automation will tell you.
   Put both EOL dates in the client's calendar at handover.

What is deliberately *not* automated: a major version bump that is not a
security fix, and any `laravel/framework` update. Those wait for a human. If a
CVE fix cannot be merged automatically the auto-merge job fails, and a failed
run emails the repo admins — which is why the admin list must be a client
address, not the departing maintainer's.

## Handover

The person who set this up is leaving. What the next maintainer needs, and
where it is **not** in this repo:

| Thing | Where it lives | Action |
|---|---|---|
| DO + Forge accounts | Prometeo Srl | confirm the client can log in to both independently, without an agency address in the loop |
| Server env vars | Forge → Environment, **only copy** | export and store in the client's password manager |
| Stripe live secret | shown once at creation | must already be in the password manager; it cannot be re-read |
| Mailtrap / Resend credentials | provider dashboard | confirm the account is registered to the client, not a personal address |
| Spaces keys | DO dashboard | see the Spaces section |
| Sudo + database passwords | Forge | hand over in writing |
| The Flutter client repo | separate repo, not committed here | its `baseUrl` must be repointed whenever the host changes |

Known work left, in the order it bites:

1. **Push `develop` to the `client` remote** — blocked on the PAT `workflow`
   scope (see the top of this file). Nothing deploys until this is done.
2. **Install `php-imagick` on the droplet** — thumbnails 500 without it.
3. **Reconcile the upload ceiling** across `MAX_KILOBYTES`, nginx and php.ini.
4. **Move documents to Spaces** — cheap now, expensive later.
5. **Register the Stripe webhook** for each environment.
6. **Turn on Quick Deploy and unattended-upgrades**, and point the repo admin
   email at the client — see *Staying patched without a maintainer*. Until then
   the automated CVE fixes stop at `develop`.

`CLAUDE.md` describes `routes/api.php` as requiring eight slice files; there
are now nine (`stripe.php`). Worth correcting when someone next touches it.
