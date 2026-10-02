# Oikos — Gatsby + WordPress

A rental, lease and utility management app, built as a headless stack:

- **`gatsby/`** — a static **Gatsby 5** site. The entire UI is a client-side React app that talks
  to WordPress in the browser.
- **`backend/estate-manager-headless/`** — a self-contained **WordPress plugin** exposing the whole
  business layer as a REST API (`em/v1`). WordPress provides authentication and user management.

The app is presented as a small operating-system shell: apps open as draggable, snappable windows
over a desktop, with a dock, desktop shortcut icons, and a ⌘K launcher. The Dashboard is the one
exception — it opens as a locked, full-width, scrollable page.

---

## Architecture

```
Browser ── Basic auth (account or application password) ──► WordPress REST ──► EM_* classes
   │                                                            em/v1             │
   └── Gatsby static site (React 18, SSG)                                   MySQL tables
```

- **No Node server at runtime.** The Gatsby build is fully static; all data comes from WordPress.
- WP core resolves the current user from the `Authorization: Basic` header, so `current_user_can()`
  gates every route.
- Requests are: browser → `Authorization: Basic` → `/wp-json/em/v1/...` → `EM_*` PHP classes → MySQL.

---

## Quick start (Docker)

### Prerequisites

- Docker Desktop
- Node.js 18+ (this repo was developed on Node 20 via `nvm`)

### 1. Start WordPress + MariaDB

```bash
docker compose up -d db wordpress
```

WordPress comes up at **http://localhost:8888**. The plugin directory is bind-mounted, so local
edits to `backend/estate-manager-headless/` are live.

### 2. Install WordPress and activate the plugin

Open http://localhost:8888 and run the installer, or use WP-CLI:

```bash
# The wpcli service's entrypoint is already `wp`, so don't repeat it.
docker compose --profile tools run --rm wpcli core install \
  --url=http://localhost:8888 \
  --title="Oikos Dev" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.test

docker compose --profile tools run --rm wpcli plugin activate estate-manager-headless
docker compose --profile tools run --rm wpcli rewrite structure '/%postname%/' --hard
```

Pretty permalinks matter: the REST API lives at `/wp-json/`, which needs them.

### 3. Start the front-end

```bash
cd gatsby
cp .env.example .env.development
npm install
npm run develop          # http://localhost:8001/app
```

Open **http://localhost:8001/app**.

---

## Local development credentials

> These exist only in the Docker dev environment. **Change every one before deploying.**

The install command above creates a WordPress admin account:

| Account | Username | Password | Role |
| --- | --- | --- | --- |
| WordPress admin | `admin` | `admin` | administrator |

Additional app users are created from the app under **Users**, where you choose
their password. No working passwords are stored in this repository.

For scripts and API testing, generate an **Application Password** for your own
account at *Users → Profile → Application Passwords*.

WordPress admin: http://localhost:8888/wp-admin

### Demo data

`docker/mu-plugins/em-local-dev.php` defines `EM_SEED_DEMO`, which seeds a sample estate (3
properties, 48 units, 48 tenants, 48 leases, 121 payments) **only when the database is empty**. It
can never overwrite real data.

To re-seed from scratch:

```bash
docker compose down -v          # wipes volumes
docker compose up -d db wordpress
# then re-run step 2 (install + activate)
```

---

## Authentication

### Signing in

The app accepts **either**:

1. **Your WordPress account password** — what you'd type at `wp-admin`.
2. **An Application Password** — a separate, revocable credential generated at
   *Users → Profile → Application Passwords*. Better for scripts and shared logins, because it can
   be revoked without changing the main password.

A successful account-password login mints a fresh application password as the session token, so the
returned credential stays revocable. To force application-passwords only (recommended for public
deployments), add to a plugin or `functions.php`:

```php
add_filter( 'em_allow_password_login', '__return_false' );
```

### Password policy

One shared policy applies to signup, admin create, self-service change and reset:

- at least 8 characters
- at least one lowercase letter, one uppercase letter, and one digit
- no trailing space
- rejected if it appears on a small common-password denylist

Setting a password (by reset or by an admin) also **revokes that user's existing application
passwords**, so a compromised session can't outlive the change.

### Forgot password

Two channels, chosen by what the account has on file:

- **SMS (default)** — if the account has a phone number, a 6-digit code is texted via Africa's
  Talking. The code is hashed at rest, expires in 10 minutes, allows 5 attempts, and is single-use.
- **Email link** — if there's no phone but there is an email address, a reset link is emailed.

Responses are deliberately identical whether or not the account exists, so the endpoint can't be
used to discover valid usernames. Failed or impossible deliveries are recorded in the audit log
(`reset_sms_undelivered`, `reset_email_undelivered`, `reset_undelivered`).

> **In local dev**, there is no SMS gateway or mail server, so the API returns the code (or link)
> and the UI shows it in a yellow "Local development" box. This never happens outside a local
> environment.

### Phone numbers

Phone numbers are stored as normalised **E.164** (`+254712345678`) in the `em_phone` user meta.
Input accepts `0712345678`, `712345678`, `254712345678`, or `+254712345678`; a genuine international
number such as `+447700900123` is left untouched. The assumed country code defaults to **254
(Kenya)** and is configurable (see Settings), or via a filter:

```php
add_filter( 'em_default_country_code', fn() => '44' );
```

Every login account needs a phone number (or email) to self-reset. The Users table flags accounts
with no phone.

---

## Tenancy exit, deposits & notices

Ending a lease early is treated as a different act from a lease simply expiring.

**Configurable defaults** (Settings → *Tenancy exit policy*) are copied onto every
new lease, which can then override them:

| Setting | Meaning | Default |
| --- | --- | --- |
| Notice period | Days of notice a tenant must give | 30 |
| Deposit | Months of rent held | 1 |
| Early-termination charge | Months of rent charged if the tenant leaves before the end date | 0 |
| Require notice | Block vacating an unexpired lease without notice or an explicit override | on |

**Vacating a unit** (Units → Vacate, or Renewals → End lease) records *how* the
lease ended — expired, early termination, eviction, or mutual surrender — and shows
the final account before anything is committed:

```
deposit held − early-termination charge − deductions = refund  (or balance due)
```

The early-termination charge applies **only** to early termination; an expired or
mutually surrendered lease pays nothing. If a lease is unexpired and no notice was
served, the action is refused unless you tick an explicit override, which is written
to the audit trail.

**Notice to Quit** is available from the lease actions menu — a printable template
that computes the vacate date from the lease's notice period. It is a template, not
a generated legal document; add your name and address and have it checked.

> **Not legal advice.** Kenyan law sets notice periods, not penalties. The Rent
> Restriction Act (Cap 296) requires one month for controlled residential tenancies;
> the Landlord and Tenant (Shops, Hotels and Catering Establishments) Act (Cap 301)
> requires two months, in a prescribed form, for commercial premises. Any
> early-termination charge is contractual and must be a genuine pre-estimate of
> loss written into the signed lease, or a court may strike it out as a penalty.
> Distress for rent arrears is governed by the Distress for Rent Act (Cap 293).
> Have a Kenyan advocate review your lease template.

## Rent, arrears & the ledger

**A payment is credited to the month it is *for*** — the `period` field — not the day it was keyed
in. A September payment made in October counts against September. Payments are attributed by unit,
so they stay correct after a unit is re-let, and they include payments recorded without a lease.

**Arrears accumulate.** Each month a tenant doesn't pay adds to their balance; an unpaid September
stays on the books in October. The Dashboard's **Rent arrears** card lists everyone who owes, with
months behind and amount, largest first. The Payments page has the full per-tenant, per-month
ledger.

**Money owed is rent charged less payments received**, so an overpayment in one month carries
forward to the next. A lease in credit owes nothing.

**Ended leases are included.** A tenant who leaves owing money stays on the arrears list, marked
*left*, and is only charged to the day they actually vacated.

The arrears window reaches back to the oldest lease on the books, capped at five years, so a
long-standing debt isn't truncated.

> **Not pro-rated.** A lease that starts, or ends, part-way through a month is charged the full
> month's rent. If your leases pro-rate, that needs a policy decision before it's coded.

## The desktop shell

| Element | What it does |
| --- | --- |
| **Windows** | Draggable, resizable, snappable. Drag to an edge to snap; double-click a titlebar to fill. |
| **Traffic lights** | Red closes, yellow minimises, green maximises/restores. Hovering reveals labels. |
| **Dock** | Pinned apps (single button each) plus chips for unpinned open windows. Click toggles. |
| **Desktop icons** | Shortcuts on the canvas behind every window. Hidden under 760px. |
| **Launcher** | ⌘K fuzzy search over the app registry. |
| **Dashboard** | Chromeless: opens locked to the full canvas, no titlebar, scrollable. |

### Keyboard shortcuts

| Shortcut | Action |
| --- | --- |
| `⌘K` / `Ctrl+K` | Toggle the app launcher |
| `` ` `` | Cycle to the window behind the current one |
| `⌘W` / `Ctrl+W` | Close the focused window |
| `⌘M` / `Ctrl+M` | Minimise the focused window |
| `Esc` | Close the launcher, or dismiss a dialog |

Shortcuts are suppressed while the "Close all" confirmation is open, so `⌘W` can't close something
behind it.

---

## Configuration (Settings page)

Open **Settings** in the app (admin only).

### Email — SMTP

Fields: enabled, host, port, username, password, from address, **Application URL**.

**Gmail:** click **Use Gmail** (sets `smtp.gmail.com:587`), then enter your full Gmail address as
the username and a 16-character **App Password** as the password. Google will not accept your
normal account password over SMTP — enable 2-Step Verification, then create an App Password at
*Google Account → Security → App passwords*. The **From** address must be the same Gmail account
(or a verified alias), otherwise Gmail rejects the message.

### SMS

Pick a gateway under **Gateway**; the fields change to match. Without credentials, messages are
marked *simulated* and delivery is still recorded.

| Gateway | Fields | Notes |
| --- | --- | --- |
| **Africa's Talking** | username, API key, sender ID, sandbox | Hosted A2P. Sandbox is free but only delivers to whitelisted numbers. |
| **SMS Gate for Android** | server URL, username, password | Self-hosted or cloud (`api.sms-gate.app`). Sends via your own SIM. |
| **textbee** | API base URL, device ID, API key | `textbee.dev`, self-hosted or cloud. Sends via your own SIM. |
| **Generic HTTP** | endpoint URL, authorization header, JSON body template | Matches any provider; use `{{to}}` and `{{message}}` placeholders. |

**Free delivery via an Android phone.** There is no free hosted SMS gateway for Kenya — the local
providers all charge per message and Twilio is ~$0.31. The free route is to put an Android phone
with a SIM in the office and send through it:

1. Install [SMS Gate](https://sms-gate.app) or [textbee](https://textbee.dev) on the phone.
2. Copy the credentials it generates into **Settings → SMS**.
3. Keep the phone powered on and online.

Cost is the SIM's SMS bundle; there is no per-message fee. The trade-off is that you own the
uptime — if the phone is off, codes stop, and **Activity** will show `reset_sms_undelivered`.

> **iOS cannot do this.** Apple forbids apps from sending SMS without the user tapping Send, so an
> iPhone can't act as a gateway. Use Android, or a Mac driving the Messages app via AppleScript if
> you have one permanently on.

### Application URL

The public URL of this Gatsby app (e.g. `https://app.example.com`). Password-reset links are built
from it. If left blank it falls back to the WordPress URL, which is wrong for a headless deploy —
set it before going live.

### Tenancy exit policy

Defaults for deposits, notice periods and early-termination charges, copied onto every new lease.
See [Tenancy exit, deposits & notices](#tenancy-exit-deposits--notices) above.

---

## API surface

All routes live under `<wp-url>/wp-json/em/v1`. Business routes require `em_manage_estate`;
user/visitor administration requires `em_manage_access`.

| Area | Routes |
| --- | --- |
| Auth | `POST /auth/login` · `GET /auth/me` · `POST /auth/change-password` · `POST /auth/forgot-password` · `POST /auth/reset-password` |
| Dashboard | `GET /dashboard` · `GET /dashboard/arrears` (the slow arrears walk, fetched separately) |
| Reports | `GET /reports/summary` · `GET /reports/period` · `GET /reports/rent-ledger` |
| Properties | `GET/POST /properties` · `PATCH/DELETE /properties/:id` |
| Units | `GET/POST /units` · `PATCH/DELETE /units/:id` · `POST /units/:id/vacate` |
| Tenants | `GET/POST /tenants` · `PATCH/DELETE /tenants/:id` |
| Onboarding | `POST /onboarding` |
| Leases | `GET/POST /leases` · `PATCH/DELETE /leases/:id` · `POST /leases/:id/renew` · `POST /leases/:id/terminate` · `PATCH /leases/:id/notice` |
| Payments | `GET/POST /payments` · `PATCH/DELETE /payments/:id` |
| Utilities | `GET/PUT /utility/rates` · `GET/POST /readings` · `PATCH/DELETE /readings/:id` |
| Notify | `GET /notifications` · `GET /notifications/stats` · `POST /reminders/run` · `POST /integrations/test` |
| Activity | `GET /activity` |
| Settings | `GET/PUT /settings` |
| Users | `GET/POST /users` · `PATCH/DELETE /users/:id` |
| Visitors | `GET /visits` |

Destructive routes expect a `reason` in the body, which lands in the audit log.

`/activity` accepts `user`, `entity`, `action`, `search` and `limit` query params.

**Lease exit.** `POST /units/:id/vacate` and `POST /leases/:id/terminate` accept:

| Field | Meaning |
| --- | --- |
| `vacate_type` | `expired` · `early_termination` · `eviction` · `mutual` |
| `effective_date` | Date possession ends (defaults to today) |
| `notice_served` | Tenant gave notice |
| `override` | Terminate an unexpired lease without notice (required when notice is enforced) |
| `deductions` | Amount withheld from the deposit |
| `notes` | Reason for the deductions |

They return the settlement (`deposit`, `fee`, `deductions`, `refund`), where a negative refund is a
balance the tenant still owes. `POST /leases`, `PATCH /leases/:id` and `POST /onboarding` accept
`deposit_amount`, `notice_period_days` and `early_termination_fee`; omit them to inherit the estate
policy.

---

## Audit trail

Every mutating action writes to `wp_em_audit_log` at the point of change. The table is
**append-only** — nothing in the API can edit or delete a row. Captured actions include creates,
updates, deletes (with the mandatory reason), lease terminate/renew/vacate, logins, password
changes, password resets, reset-request attempts, undelivered reset messages, and settings changes.

View it in the **Activity** app, or via `GET /activity` with filters.

---

## Backend plugin

### Standalone install (outside Docker)

1. Copy `backend/estate-manager-headless/` into `wp-content/plugins/`.
2. Activate **Estate Manager — Headless**.
3. On activation the plugin creates its tables and default settings (no demo data unless
   `EM_SEED_DEMO` is defined truthy).

> Do not run the classic `estate-manager` plugin side by side — both define the same `EM_*` classes.

### Capabilities

- `em_manage_estate` — all business routes. Granted to the `estate_manager` role and admins.
- `em_manage_access` — user and visitor administration. Admins only.
- `em_view_visitors` — visitor log. Admins only.

### CORS

The plugin sends `Access-Control-Allow-Origin: *` on `em/v1` responses and answers `OPTIONS`
pre-flight, so the Gatsby dev server can call across origins. Tighten this for production (see
`estate-manager-headless.php`).

---

## Repo layout

```
estate-manager-gatsby/
├── docker-compose.yml                 # WordPress 6.8 + MariaDB 11 + WP-CLI
├── docker/mu-plugins/em-local-dev.php # Docker-only: demo seed + app passwords over HTTP
├── backend/estate-manager-headless/   # WordPress REST plugin
│   ├── estate-manager-headless.php    # bootstrap: CORS, cron, activation
│   └── includes/
│       ├── class-em-api.php           # REST routes (em/v1) + auth + reset flow
│       ├── class-em-db.php            # schema, CRUD, audit log, seed
│       ├── class-em-settings.php      # settings + masked secrets
│       ├── class-em-notify.php        # SMS/email delivery, reminders
│       ├── class-em-reports.php       # dashboard / summary / ledger queries
│       └── class-em-helpers.php       # money, dates, phone normalisation, UA
└── gatsby/
    ├── gatsby-config.js / gatsby-node.js / gatsby-browser.js
    ├── src/pages/index.jsx            # public landing page
    ├── src/pages/app.jsx              # wrapper for the client app
    ├── src/client/
    │   ├── App.jsx                    # auth gate, idle logout, reset routing
    │   ├── api.js                     # WP base URL, Basic auth, error mapping
    │   ├── os/                        # window manager, dock, desktop, explorer, launcher
    │   │   ├── wm-core.js             # pure reducer (unit-tested)
    │   │   └── wm-core.test.mjs
    │   ├── pages/                     # one component per app window
    │   ├── components/                # AuthShell, VacateModal, NoticeToQuit, RowMenu…
    │   ├── fonts/                     # self-hosted Cinzel + Great Vibes (Latin subset)
    │   ├── brand.css · styles.css · os.css · landing.css
    └── .env.example
```

---

## Environment variables

| Variable | Meaning | Default |
| --- | --- | --- |
| `GATSBY_WP_URL` | Base URL of the WordPress install running the plugin | `http://localhost:8888` |
| `GATSBY_SITE_URL` | Public URL of the Gatsby site (metadata) | `http://localhost:8001` |

`GATSBY_`-prefixed variables are inlined at build time. They contain no secrets — auth happens in
the browser. Changing `GATSBY_WP_URL` requires a rebuild (`gatsby clean && npm run build`).

`.env.development` is gitignored; `.env.example` is committed.

---

## Testing

```bash
cd gatsby
npm run test:os      # 45 tests for the pure window-manager reducer
npm run build        # production build
```

`wm-core.test.mjs` covers window creation, cascade, focus/z-order, snapping, chromeless (locked
page) windows, and canvas-resize behaviour. It runs on plain Node by mirroring the ESM core to a
temp `.mjs` file.

---

## Troubleshooting

> A fuller log of every bug hit during development — symptom, cause, fix — is in
> [`ISSUES.md`](ISSUES.md).

**"Cannot reach the server at …"**
WordPress isn't running, or `GATSBY_WP_URL` is wrong. Check `docker compose ps` and
http://localhost:8888. Rebuild Gatsby if you changed the env var.

**Sign-in fails with a valid-looking password**
The app accepts account passwords and application passwords. If neither works, the account may be
disabled, or a recent password change revoked the application passwords — sign in with the account
password instead.

**"Forgot password" sends nothing**
Check the account has a phone number (or email) in **Users**. Then check **Settings** — SMS needs
Africa's Talking credentials; email needs SMTP configured and the toggle on. Look in **Activity**
for `reset_sms_undelivered` / `reset_email_undelivered`, which state the exact reason. In local dev
the code appears on screen instead.

**Reset link points at WordPress, not the app**
Set **Application URL** in Settings to the address people open the app at.

**Vacating is refused: "This lease has not reached its end date"**
Notice enforcement is on and the lease is unexpired. Either tick **Notice was served**, or tick
**Override** to terminate early — both are written to the audit trail. Change the default, or the
notice period, in **Settings → Tenancy exit policy**. If the lease was simply expiring, choose the
**Expired** exit type instead.

**Deposit shows as 0 on an old lease**
Demo leases seeded before the exit-policy feature have no deposit. Edit the lease and set one, or
re-seed the database.

**Gmail rejects the test email**
Use a 16-character App Password, not the account password, and make sure the **From** address is the
same Gmail account.

**500 error deleting a user (older checkouts)**
`wp_delete_user()` ships in `wp-admin/includes/user.php`, which a REST request doesn't load. Fixed
in `class-em-api.php` by requiring that file first — the same trap applies to `dbDelta()`.

**Lease exit columns missing after upgrading**
The deposit / notice / settlement columns are added by `dbDelta` on activation. Deactivate and
reactivate the plugin, or run:

```bash
docker compose --profile tools run --rm wpcli eval 'EM_DB::activate();'
```

**`dbDelta` / activation errors**
`dbDelta()` also lives in `wp-admin/includes/upgrade.php`; it is required explicitly in
`class-em-db.php` so activation works under WP-CLI and REST.

**Gatsby dev server shows a stale bundle or 500**
Restart it, and if that fails: `cd gatsby && npm run clean && npm run develop`.

**REST API returns 404**
Pretty permalinks aren't enabled. Run
`docker compose --profile tools run --rm wpcli rewrite structure '/%postname%/' --hard`.

**`wp` is not a registered command**
The `wpcli` service entrypoint is already `wp`. Use
`docker compose --profile tools run --rm wpcli <args>`, without a leading `wp`.

---

## Deploying

There are two ways to go live. Both are documented in full in [`deploy/README.md`](deploy/README.md).

| | Single WordPress install | Subdomain |
| --- | --- | --- |
| Shape | `your-site/oikos/` | `app.example.com` + WordPress elsewhere |
| Setup | Upload one plugin zip, activate | Build, upload to a host, set CORS |
| CORS | None — same origin | Must allow-list the app origin |
| Config | None | `GATSBY_WP_URL`, Application URL, `em-cors.php` |

### Option A — as a WordPress plugin (fewest moving parts)

The app is bundled inside a plugin and served from the same domain, so there is no CORS
and no second host.

```bash
./deploy/build-plugin.sh          # builds the app and writes deploy/oikos-app.zip
```

Then **Plugins → Add New → Upload Plugin** → `deploy/oikos-app.zip` → **Activate**, and open
`https://your-site/oikos/`. The plugin serves the app and points reset links at itself.

### Option B — as a subdomain

The intended production shape:

```
https://example.com        WordPress + the plugin   (the API)
https://app.example.com    the Gatsby build         (the app)
```

The app is a **static bundle** — it has no server. It calls WordPress's REST API directly from the
browser, so the two can live on different hosts.

### 1. WordPress (the API)

1. Install WordPress on the main domain, **over HTTPS**. Application Passwords refuse to work
   without SSL.
2. Upload `backend/estate-manager-headless/` to `wp-content/plugins/` (zip it and use
   *Plugins → Add New → Upload*, or deploy by SFTP/git) and activate
   **Estate Manager — Headless REST API**.
3. **Settings → Permalinks → Post name.** `/wp-json/` needs pretty permalinks.
4. **Do not copy `docker/mu-plugins/em-local-dev.php`.** It seeds demo data and allows application
   passwords over plain HTTP — both must not exist in production.
5. Create users, then generate **Application Passwords** for them
   (*Users → Profile → Application Passwords*).

### 2. Point the API at the app

In the app's **Settings → Email → Application URL**, set `https://app.example.com`. Reset links are
built from it; without it they point at WordPress.

Restrict CORS to your app's origin. Drop this in `wp-content/mu-plugins/em-cors.php`:

```php
<?php
add_filter( 'em_cors_allow_origin', function () {
    return 'https://app.example.com';
} );
```

Without it the plugin reflects whatever `Origin` the caller sends.

### 3. Build the app

The WordPress URL is **baked in at build time**, so build with the production values:

```bash
cd gatsby
GATSBY_WP_URL=https://example.com \
GATSBY_SITE_URL=https://app.example.com \
npm ci && npm run build
```

The static site is now in `gatsby/public/`.

### 4. Serve `gatsby/public/` on the subdomain

Pick whichever matches your hosting:

- **Netlify / Vercel / Cloudflare Pages** — connect the repo, build command
  `cd gatsby && npm ci && npm run build`, publish directory `gatsby/public`, and set the two
  `GATSBY_*` variables in the host's environment settings. Add `app.example.com` as a custom domain.
- **cPanel / shared hosting** — create the subdomain `app.example.com` (it gets its own folder),
  then upload the *contents* of `gatsby/public/` into it. Issue a free Let's Encrypt certificate
  from cPanel.
- **VPS (nginx)** — point a server block at the folder:

  ```nginx
  server {
    listen 443 ssl;
    server_name app.example.com;
    root /var/www/oikos/public;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
  }
  ```

### 5. Verify

Open `https://app.example.com/app/` and sign in.

- **CORS error in the console** → the origin in `em_cors.php` doesn't match exactly (scheme and
  host, no trailing slash).
- **Sign-in fails** → WordPress isn't on HTTPS, or you used the account password where the filter
  `em_allow_password_login` has been turned off.
- **Reset links go to the wrong place** → Application URL not set.
- **Stale data after changing `GATSBY_WP_URL`** → it's compiled in; rebuild.

---

## Security checklist

Before going live:

1. **Change every dev credential** from the local-dev table above.
2. **Do not deploy `docker/mu-plugins/em-local-dev.php`.**
3. **Set the Application URL** so reset links resolve to the app.
4. **Serve everything over HTTPS.** Basic auth credentials ride on every request.
5. **Restrict CORS** to the app origin (`em_cors_allow_origin`).
6. **Force application passwords** with `add_filter( 'em_allow_password_login', '__return_false' )`
   if you don't want account passwords travelling in the Authorization header.
7. **Protect the settings table.** SMTP and SMS credentials are stored in `wp_em_settings`; treat
   that table as secret.
8. **Back up** the WordPress database regularly:
   ```bash
   wp db export backup-$(date +%F).sql
   ```
