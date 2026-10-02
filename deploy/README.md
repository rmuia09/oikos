# Deploy kit

Two ways to go live. Pick one.

---

## Option A — a single WordPress install (least effort)

The app ships **inside a WordPress plugin** and is served from the same domain at
`https://your-site/oikos/`. Because the app and the REST API share an origin there is
**no CORS, no second host, and nothing to configure** beyond activating the plugin.

```bash
./deploy/build-plugin.sh          # builds the app and produces deploy/oikos-app.zip
```

Then:

1. **WordPress → Plugins → Add New → Upload Plugin** → choose `deploy/oikos-app.zip`
2. **Activate** it.
3. Open `https://your-site/oikos/`

That's it. The plugin:

- serves the app at `/oikos/` (change `OIKOS_SLUG` in `oikos-app/oikos-app.php` to move it)
- points the reset-link setting (`em_app_url`) at itself automatically
- requires **no** `.env` values — the API is called on the same origin

Requirements: WordPress over **HTTPS**, pretty permalinks on, and the
`estate-manager-headless` plugin activated (it provides the API).

---

## Option B — a subdomain (app and WordPress on different hosts)

```
https://example.com        WordPress + the plugin   (the API)
https://app.example.com    the static build         (the app)
```

1. WordPress: install `backend/estate-manager-headless/`, activate, set permalinks to
   *Post name*, and create users with Application Passwords.
2. Copy `mu-plugins/em-cors.php` to `wp-content/mu-plugins/` and edit `OIKOS_APP_ORIGIN`
   to your app URL. This is the CORS allow-list.
3. In the app's **Settings → Email → Application URL**, set `https://app.example.com`.
4. Build with the production URL baked in:

   ```bash
   cd gatsby
   GATSBY_WP_URL=https://example.com GATSBY_SITE_URL=https://app.example.com npm run build
   ```

5. Serve `gatsby/public/` on the subdomain — use one of:
   - `netlify.toml` (copy to the repo root for Netlify)
   - `vercel.json` (for Vercel; set the two `GATSBY_*` variables in the dashboard)
   - `nginx.conf` (VPS; edit the paths and certificate lines)

---

## Verify either way

- `https://…/app/` loads the sign-in screen.
- Signing in reaches the desktop.
- If sign-in fails, the site is not on HTTPS, or you used an account password where
  `em_allow_password_login` has been turned off.
- A CORS error in the console (Option B only) means `em-cors.php` doesn't match the app
  origin exactly — scheme and host, no trailing slash.

## Before you go live

- Change every credential from the local-dev setup.
- **Never** deploy `docker/mu-plugins/em-local-dev.php` — it seeds demo data and allows
  Application Passwords over plain HTTP.
- Restrict CORS to the app origin (Option B) or skip it (Option A, same origin).
- Back up the database: `wp db export backup-$(date +%F).sql`.
