# Issues & fixes

A log of the real problems hit while building this, what caused them, and how
they were fixed. Grouped by area. Most were found by testing against live data
rather than by reading the code — which is why several were silent.

---

## WordPress plugin (PHP)

### `dbDelta()` fatal on activation
**Symptom:** Activating via WP-CLI or a REST request errored; the tables were never created.
**Cause:** `dbDelta()` lives in `wp-admin/includes/upgrade.php`, which is only loaded for admin requests.
**Fix:** `require_once ABSPATH . 'wp-admin/includes/upgrade.php';` before calling it (`class-em-db.php`).

### Deleting a user returned HTTP 500
**Symptom:** Every user delete crashed.
**Cause:** `wp_delete_user()` lives in `wp-admin/includes/user.php`, not loaded in a REST context.
**Fix:** require that file first (`class-em-api.php`). Same class of bug as `dbDelta`.

### `days_left` never reached the API — the "undefinedd left" badge
**Symptom:** Lease badges rendered `undefinedd left`.
**Cause:** `foreach ( (array) $expiring as &$e )` — the `(array)` cast creates a **temporary**, so by-reference writes were discarded. The field simply never existed in the response.
**Fix:** cast first, then iterate by reference, then `unset( $e )`. Same pattern existed twice more (`class-em-api.php`) and was fixed there too.

### `add_months()` returned 1970-01-01 for negative offsets
**Symptom:** The income report's date range started at 1970.
**Cause:** PHP's `%` keeps the sign of a negative dividend, so `( -3 % 12 )` is `-3`, producing an invalid month like `-2`; `strtotime()` then failed.
**Fix:** normalise via total months since year 0 with `intdiv()`. Verified for `-1 … -24` and `+12`.

### Password reset always said "expired or already used"
**Symptom:** Every reset link failed.
**Cause:** `check_password_reset_key( $key, $user )` was passed a `WP_User` object; core requires a **string** login and returns `invalid_key` for anything else.
**Fix:** pass `$user->user_login`.

### Failed reset emails/SMS were invisible
**Symptom:** Users were told a link was sent; nothing arrived; no trace.
**Cause:** the forgot-password response is intentionally identical for every account, and delivery failures were discarded.
**Fix:** write an audit entry (`reset_sms_undelivered`, `reset_email_undelivered`, `reset_undelivered`) with the reason.

### Renewal dropped the lease's exit terms
**Symptom:** After renewing, the deposit and notice period read 0.
**Cause:** `leases_renew()` inserted a new lease without copying `deposit_amount`, `notice_period_days`, `early_termination_fee`.
**Fix:** carry them forward.

### Expired leases still counted as active
**Symptom:** Expected rent was inflated by leases that had already ended.
**Cause:** nothing ever changes a lease's status when its end date passes.
**Fix:** treat a past end date as *expired* — excluded from expected rent and renewals-due, flagged on the dashboard, and shown as `expired` in the Leases view.

---

## Data & calculations

### Rent ledger showed everyone owing everything
**Symptom:** 37 of 39 tenants "owed" their full history with zero paid.
**Cause:** `GROUP BY 4` grouped by the **month** column, not the lease, collapsing every lease's payments into one arbitrary row.
**Fix:** group by lease and month (`GROUP BY 1, 4`).

### Payments attributed to the wrong month
**Symptom:** A September payment made in October counted against October.
**Cause:** everything grouped by `payment_date` instead of the `period` the payment is *for*.
**Fix:** use `period`, falling back to the payment date.

### Payments broke on re-lets
**Symptom:** After a unit changed tenant, payments credited the wrong lease.
**Cause:** matched by `lease_id`, which points at whatever lease was open when the payment was keyed in.
**Fix:** attribute by **unit + month**. Also picks up payments with no `lease_id`.

### Overpayments vanished
**Symptom:** Paying extra in one month didn't reduce the next month's arrears.
**Cause:** `max( 0, expected − paid )` per month discarded the excess.
**Fix:** money owed is `charged − paid`, so credit carries forward.

### Arrears understated for long-standing debts
**Cause:** a fixed 12-month window.
**Fix:** reach back to the oldest lease on the books, capped at five years.

### Departed tenants owing money disappeared
**Cause:** the ledger only looked at active leases.
**Fix:** include terminated leases, charged only to `vacated_at`, marked *left*.

### Seeded payments fell outside the tenancy
**Symptom:** 34 seeded payments were dated *before* their lease started, so the money was "collected" but credited to no month.
**Cause:** the seed generated payments for the last few calendar months regardless of the lease term.
**Fix:** generate payments month-by-month within the lease, up to today.

### Income report `grand_total` only summed what was shown
**Symptom:** A 4-week view reported four weeks as the grand total.
**Cause:** the total was accumulated inside the limited period loop.
**Fix:** total the whole requested range.

---

## Authentication

### Managers couldn't sign in
**Symptom:** A valid username + password was rejected.
**Cause:** login only accepted WordPress **Application Passwords**.
**Fix:** fall back to the account password, then mint a fresh Application Password so the token stays revocable. Filterable via `em_allow_password_login`.

### Password rules differed per path
**Cause:** complexity was only enforced on reset; signup and self-service change used a 6-character minimum.
**Fix:** one shared `validate_password_strength()` used by all four paths, plus a common-password denylist. Setting a password also revokes existing Application Passwords.

### "Forgot password?" did nothing
**Cause:** the button was wired to `clearReset`, which sets the mode to `null` — a no-op.
**Fix:** split into `onShowForgot` / `onShowLogin`.

---

## Front-end / OS shell

### Crash: `windows is not defined`
**Cause:** the menubar referenced `windows.length` before the variable existed.
**Fix:** define `windows` once and use it consistently.

### `⌘W` closed the browser tab
**Symptom:** With no window focused, ⌘W closed the tab.
**Cause:** `e.preventDefault()` was inside the `&& win` guard, so it never ran when nothing was focused and the browser handled the key itself.
**Fix:** claim the key first, then act.

### Duplicate windows
**Symptom:** Opening the same app from the launcher, dashboard, or explorer stacked copies.
**Fix:** a `surfaceApp()` helper (focus-or-open) used by every navigation path; the dock keeps toggle-to-minimise.

### No cross-window refresh
**Symptom:** Recording a payment didn't update the dashboard.
**Cause:** each window fetched once on mount.
**Fix:** successful writes dispatch `estate:data-changed`; every `useResource` refetches silently (debounced), plus on tab focus.

### Window controls overlapped the title
**Cause:** the title used `flex: 1` without `min-width: 0`, so it pushed into the traffic lights.
**Fix:** `min-width: 0` on the title, `flex-shrink: 0` on the controls.

### Traffic-light clicks started a drag
**Cause:** the buttons inherited the titlebar's `grab` cursor and pointer handler.
**Fix:** stop propagation on the controls and override the cursor.

### Maximised window kept a stale size on resize
**Cause:** the `bounds` reducer updated the canvas but not snapped windows.
**Fix:** re-derive snapped rects from the new bounds.

### "Load ledger" sent the click event as a date
**Symptom:** After adding optional override arguments, the ledger button produced a corrupt query (`from=[object PointerEvent]`).
**Cause:** `onClick={loadLedger}` passes the click event as the first argument.
**Fix:** wrap it in a closure. A reminder that adding parameters to a handler used directly as `onClick` is a trap.

### Bar chart labels were clipped
**Cause:** the bar used `height: X%` inside a column that also held the label, so a full-height bar pushed the label out.
**Fix:** labels moved to their own row below a real X axis.

### Reports chart drew ~50 bars on mobile
**Cause:** the initial request omitted the date range, so the API defaulted to a full year.
**Fix:** compute and send the range on first load.

### "Close all" couldn't be seen
**Cause:** it sat at the end of a horizontally-scrolling nav with hidden scrollbars, and was hidden entirely under 760px.
**Fix:** moved the window-wide actions into the always-visible right group.

---

## Performance

### The dashboard was slow — "loading ledger"
**Cause:** the arrears calculation (every lease × every month) ran inside the main dashboard request and blocked all the KPI cards.
**Fix:** split it — `/dashboard` returns the cards (206ms → 44ms), `/dashboard/arrears` fetches the arrears separately with its own loading state.

### The ledger drill-down over-fetched
**Symptom:** Clicking a tenant in the arrears list was slow to open their ledger.
**Cause:** it asked the API for **every** tenant's ledger and then hid the others in the browser.
**Fix:** pass `tenant_id` so the server narrows it (53ms → 21ms).

### Opening the Payments window pulls a lot at once
**Symptom:** The Payments window is slow to appear when opened from a drill-down.
**Cause:** it loads payments (up to 500 rows) plus units, tenants and properties together, because all four feed the filter dropdowns.
**Status:** known; the fix is to lazy-load the filter lists until a filter is opened.

---

## Deployment

### Assets 301-redirected to a trailing slash
**Cause:** WordPress treated the app route as a page and canonicalised it.
**Fix:** `redirect_canonical` returns false for the app's own requests.

### App stuck on "Loading…"
**Symptom:** The HTML loaded but React never hydrated.
**Cause:** serving JS through WordPress got the `Content-Type` rewritten by the host, so the browser refused to execute it.
**Fix:** the plugin now copies the built app into the site root on activation, so the web server serves it as plain static files with correct types.

### 403 Forbidden on the deployed app
**Cause:** PHP created the copied directory owner-only; the web server runs as a different user.
**Fix:** `chmod 0755` on directories and `0644` on files during the copy.

### `assetPrefix` + `pathPrefix` produced doubled paths
**Symptom:** assets referenced `…/build/oikos/…`.
**Cause:** Gatsby concatenates the two.
**Fix:** abandoned `assetPrefix`; deployed static files instead.

### Stale/mismatched Gatsby bundle
**Cause:** `gatsby build` leaves old hashed files in `public/`, which then get shipped alongside new ones.
**Fix:** `gatsby clean` before every deploy build.

### `docker compose cp` nested the plugin folder
**Symptom:** The plugin file ended up at `plugins/oikos-app/oikos-app/oikos-app.php`.
**Fix:** remove the destination first, or copy `./` into it.

---

## Operational notes (things that wasted time)

- **`wp` is a server tool.** It doesn't exist on a local Mac, and typing `WP-CLI` as a command won't work.
- **Must-use plugins only appear** under *Plugins → Must-Use* if the file is in `wp-content/mu-plugins/` with a `.php` extension. If that menu is missing, the file isn't loaded.
- **Theme File Editor is often disabled** by hosts; use the File Manager or a tiny temporary plugin instead.
- **`EM_SEED_DEMO` must be in the right `wp-config.php`** — on a subdomain that's usually the subdomain's own document root, not the main domain's.
- **The demo seed only runs on an empty estate** and records a flag; if it refuses, a property already exists.
- **There are two plugins, and they version together.** `estate-manager-headless` is the API; `oikos-app` is the app. A new app calling a new route against an old backend returns `404 rest_no_route` — which is what happened with `/dashboard/arrears`. When only one is re-uploaded, check which changed: `backend/` → API plugin, `gatsby/` → app plugin.

---

## Pattern worth remembering

Several of these shared one root cause: **a value that looked present but wasn't**. `days_left`, `grand_total`, `GROUP BY`, `add_months`, the reset key — each produced plausible-looking output that was silently wrong. The reliable way to catch them was comparing a computed figure against the underlying rows by hand, not reading the code.
