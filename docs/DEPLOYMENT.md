# Deploying Iconors to Hostinger

**Assessed:** 18 July 2026 · 183 tests passing

---

## Short answer

**Not ready for a public launch. Ready for a private staging deploy.**

The site cannot take money — there is no cart, no checkout, and no payment
routes at all. It also cannot send email. Both are configuration-and-build
work, not bugs.

What you *can* do today is put it on Hostinger behind a password so you can
click through it on a real server, on a real domain, over HTTPS.

---

## Blockers for a public launch

Verified against the live install, not assumed.

| # | Blocker | Evidence | Severity |
|---|---|---|---|
| 1 | **No checkout** | `cart`, `checkout`, `orders`, `invoices`, `subscriptions` → **0 routes each** | Cannot trade |
| 2 | **No email delivery** | `mail_driver=log`, `mail_host` NOT SET | No verification, no receipts, no password reset |
| 3 | **No payment gateway** | `stripe_enabled=0`, `paypal_enabled=0`, both secrets NOT SET | Cannot charge |
| 4 | **Debug mode on** | `APP_DEBUG=true`, `APP_ENV=local` | **Leaks stack traces including DB credentials** |
| 5 | **13 demo accounts live** | all `@iconors.test`, password `password` | Anyone who reads the docs is an admin |
| 6 | **Placeholder legal text** | Terms / Privacy / Refunds | Legal exposure |

Items 4 and 5 are the dangerous ones. Do not put this on a public URL
without fixing both — a Laravel error page in debug mode prints your
database password to anyone who triggers it.

---

## Pre-deploy checklist

### 1. Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

`APP_DEBUG=false` is the single most important line in this file.

### 2. Remove the demo accounts

```sql
DELETE FROM users WHERE email LIKE '%@iconors.test';
```

Then create your real administrator through `php artisan tinker`, or
register normally and grant the role in the database. `DemoUsersSeeder`
already refuses to run when `APP_ENV=production`, but it cannot remove
accounts that are already there.

### 3. Database

Create a MySQL database in hPanel, then:

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=SettingsSeeder --force
php artisan db:seed --class=ContentSeeder --force
php artisan db:seed --class=PlanSeeder --force
```

Do **not** run `DemoUsersSeeder` or `DemoCatalogueSeeder` in production.

### 4. Storage

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

Private asset files live in `storage/app/assets-private` and must **never**
be web-reachable. Confirm after deploy:
`https://yourdomain.com/storage/assets-private/` must return 403 or 404.

### 5. Build and cache

```bash
npm ci && npm run build          # or build locally and upload public/build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Document root

Point the domain at **`/public`**, not the project root. If Hostinger will
not let you change it, the usual workaround is to put the app outside
`public_html` and move `public/`'s contents into it, editing `index.php`'s
two `require` paths.

Leaving the document root at the project root exposes `.env`.

### 7. Cron

```
* * * * * cd /home/USER/iconors && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Queue

Hostinger shared hosting has no Supervisor. Use a cron-driven worker:

```
*/5 * * * * cd /home/USER/iconors && php artisan queue:work --stop-when-empty --max-time=280 >> /dev/null 2>&1
```

Queued work today: contributor royalty accrual on subscription downloads.

### 9. SMTP

Set at **Admin → Settings → Email**, then send a test from that screen.
Add **SPF and DKIM DNS records** — without them your verification emails
land in spam, which is the most common cause of "users never got the email".

---

## Hostinger specifics

| Requirement | Hostinger shared | Notes |
|---|---|---|
| PHP 8.2+ | ✅ selectable in hPanel | |
| MySQL / MariaDB | ✅ | |
| Composer | ✅ via SSH on Business+ | Otherwise upload `vendor/` |
| `storage:link` symlink | ⚠️ sometimes blocked | Fallback: copy files, or a PHP route that streams them |
| Cron | ✅ | |
| Queue worker (Supervisor) | ❌ | Use the cron worker above |
| Redis | ❌ | Keep `CACHE_STORE=database` |
| Meilisearch | ❌ | Keep `SCOUT_DRIVER=database` |
| SSL | ✅ free | Mandatory — gateways reject plain HTTP |

**Shared hosting is workable.** Nothing currently built requires a VPS.
You would want one once the catalogue outgrows database `LIKE` search
(a few thousand assets) or when preview generation starts running.

**Plan:** Business or higher, for SSH and Composer.

---

## What works on day one

- Marketplace: browse, filter, sort, search, asset detail
- 717 published assets already imported
- Registration and login, with breach-checked passwords
- Secure downloads: signed, expiring, user-bound URLs
- Free vs premium entitlement enforcement
- Contributor apply → upload → admin review → publish
- Admin dashboard: users, roles, moderation, plans, licences, branding, SEO
- SEO: dynamic robots.txt, four sitemaps, structured data
- Dark theme, responsive, WCAG AA

## What does not

- Buying anything
- Subscribing to anything
- Any email
- Likes and collections (buttons are inert)
- Team accounts
- The SVG / Lottie / 3D editors

---

## Recommendation

**Deploy to a staging subdomain now** — `staging.yourdomain.com`, HTTP
Basic Auth in hPanel, `APP_DEBUG=false`, demo accounts removed. You get to
click through it on real infrastructure and find the environment problems
early, which is genuinely valuable.

**Do not point your main domain at it** until checkout exists and the legal
pages have been through a solicitor. A marketplace that cannot take payment
is a catalogue; launching it publicly sets an expectation you cannot meet
yet.
