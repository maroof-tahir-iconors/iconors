# Iconors — Project Status & Completion Guide

**Last updated:** 18 July 2026
**Build state:** ~40% of the full brief. Foundation, public marketplace, contributor
workflow, admin dashboard, global settings and SEO are complete and verified.
Commerce, customer/team dashboards, payouts and the three editors remain.

**143 automated tests passing.**

---

# PART 1 — What You Need To Provide

**Read this part first.** Nothing else can be finished until these arrive. Everything
here needs *your* account, card or decision — none of it can be created on your behalf.

## 1.1 Blocking — work is stalled without these

| # | What | Where to get it | Blocks |
|---|---|---|---|
| 1 | **Stripe test keys** — publishable, secret, webhook signing secret | dashboard.stripe.com/test/apikeys | Checkout, subscriptions, invoices, refunds |
| 2 | **PayPal sandbox credentials** — client ID, secret, webhook ID | developer.paypal.com/dashboard | PayPal checkout |
| 3 | **SMTP credentials** — host, port, username, password | Your provider (see 1.4) | All 24 email templates, verification, password reset |

**How to enter them** — do NOT paste them into a chat or commit them to git:

- Log in as an administrator → **Admin → Settings → Payments** (`/admin/settings/payments`)
- Or **Admin → Settings → Email** (`/admin/settings/mail`)

They are encrypted before storage and never displayed again. A masked hint
(`••••••••1234`) confirms a value is saved. Leaving a field blank keeps the existing value.

Alternatively put them in `.env` — placeholders are already there, and `.env` is gitignored.

## 1.2 Business rules — all admin-configurable, no code changes

These are **not** decisions you have to make before development continues. Every one is
editable in the admin dashboard, takes effect immediately, and is covered by tests
proving it changes real behaviour rather than just saving a row.

| Setting | Where | Default | Notes |
|---|---|---|---|
| **Subscription plan pricing** | Admin → Plans | — | Full CRUD: monthly/annual price, trial, limits, entitlements, feature list |
| **Contributor commission rate** | Admin → Settings → Royalties | 50% one-time, 30% subscription, 25% API | Applies to future earnings only |
| **Payout holding period** | Admin → Settings → Royalties | 30 days | Warns if shorter than your refund window |
| **Minimum payout threshold** | Admin → Settings → Royalties | $50 | |
| **Refund policy window** | Admin → Settings → Royalties | 14 days | |
| **Free download daily limit** | Admin → Settings → Marketplace | 10/day | 0 = unlimited |
| **Licence terms wording** | Admin → Licences | — | Full CRUD with permission flags |
| **Signed download link lifetime** | Admin → Settings → Marketplace | 5 minutes | Keep short |
| **Require asset review** | Admin → Settings → Marketplace | On | Warns loudly when turned off |

Two safeguards worth knowing about:

- **Commission changes are never retroactive.** Each `order_item` freezes the rate that
  applied at purchase, so changing the global rate cannot alter what a contributor has
  already earned.
- **Editing licence text does not change licences already issued.** Every download stores
  a snapshot of the terms as they were at that moment.

### Still genuinely yours to decide

| # | Item | Why it can't be defaulted |
|---|---|---|
| 1 | **Company details for invoices** — legal name, address, VAT/tax number | Legally required on invoices; only you have them |
| 2 | **Review of the licence wording** | Defaults are a starting point, not legal advice — have a solicitor read them before launch |

Start on these two now. They need drafting time, and invoices cannot be generated
without item 1.

## 1.3 Accounts to open (before launch, not before development)

- **Stripe live account** — requires business verification, can take several days
- **PayPal business account** — same
- **S3 or compatible storage** (AWS S3, DigitalOcean Spaces, Cloudflare R2) — for asset files
- **Meilisearch** — self-host on your VPS, or Meilisearch Cloud
- **Domain + SSL** — SSL is mandatory; payment gateways reject plain HTTP
- **Error monitoring** (Sentry free tier is sufficient to start)

## 1.4 Choosing an email provider

Development uses the `log` driver — email is written to `storage/logs/laravel.log`
and never sent. That is fine for now. For production, pick one:

| Provider | Free tier | Notes |
|---|---|---|
| **Brevo** | 300/day | Easiest start, generous free tier |
| **Mailgun** | Trial only | Reliable, good deliverability |
| **Postmark** | 100/month | Best deliverability for transactional |
| **Amazon SES** | 62k/month from EC2 | Cheapest at scale, fiddly setup |

**Recommendation: Brevo to start.** The free tier covers early volume, and swapping
later is a settings change, not a code change.

Whichever you choose, you must also set up **SPF and DKIM DNS records**. Without them,
your verification and receipt emails will land in spam — the single most common cause
of "users never received the email" complaints.

---

# PART 2 — What Is Built And Verified

Everything below was exercised end to end, not just written.

## 2.1 Foundation
- Laravel 12.64 on PHP 8.2, MariaDB 10.4
- **91 tables, 130 foreign keys, 313 indexes**
- All money columns `DECIMAL(12,4)` — never floats
- `ledger_entries` and `audit_logs` are append-only by design

## 2.2 Branding (brief §2)
- Palette extracted from live iconors.com — evidence in `docs/BRAND.md`
- Admin colour changes restyle the entire site with no rebuild
- Colour input is validated against a strict hex allowlist (prevents stored XSS)
- Text colour on the primary is recomputed from WCAG luminance, so contrast cannot
  be broken by a colour picker

## 2.3 Roles & permissions (brief §26)
- **19 roles, 140 permissions**, enforced in three layers: route → policy → form request
- "edit own" and "edit all" are separate permissions, not a runtime ownership check

## 2.4 Public marketplace (brief §5, §9, §10)
- Home, browse with filters and sort, asset detail with JSON-LD, AJAX typeahead
- Filters are URL query params, so any filtered view is shareable
- Zero-result searches are logged for the catalogue roadmap

## 2.5 Secure downloads (brief §32) — *the most security-critical piece*
- Premium files live on a private disk with **no public route**
- Access only via a short-TTL signed URL, **bound to one user id**
- Entitlement re-checked at stream time, not just at URL creation
- Free / purchase / subscription / team paths all covered
- 19 tests including expiry, cross-account reuse, and unpaid-order rejection

## 2.6 Contributor workflow (brief §19, §20)
- Apply → admin review → approve → upload → moderation → publish → appears in search
- **SVG sanitised before storage**, not before render
- 14 real XSS vectors blocked (script tags, event handlers, XXE, billion-laughs,
  `foreignObject`, external fetches)
- A reviewer cannot approve their own submission

## 2.7 Admin dashboard (brief §24, §25)
- `/admin` with permission-filtered sidebar; action queues lead the page
- Configuration-health panel flags what will fail in production
- Moderation queues for assets and contributor applications
- Plans and licences: full CRUD, nothing about pricing hard-coded
- Every screen render-tested (`AdminPagesRenderTest`)

## 2.8 Admin settings (brief §21, §27, §30)
- Payment gateway credential fields — encrypted, write-only, masked hints
- SMTP settings plus a working test-email button
- A gateway cannot be enabled while its credentials are empty
- General: identity, contact, locale, timezone, registration, maintenance mode
- Branding: 17 colours, typography, 8 logo/image uploads (SVG logos sanitised)

## 2.9 SEO & analytics (brief §28, §29)
- Dynamic `robots.txt` and a sitemap index plus four child sitemaps, all from live data
- One master switch de-indexes staging via both robots.txt and per-page `noindex`
- Organization / WebSite / SearchAction structured data
- Five analytics providers; a disabled one emits **nothing at all**
- Analytics IDs pinned to each provider's real format — they land in inline scripts

---

# PART 3 — What Is Left

Ordered by dependency. Percentages are rough share of remaining effort.

## Phase A — Commerce *(needs item 1.1 #1)* — 20%
1. Cart (add/remove/update) — tables exist, UI does not
2. Checkout flow
3. Stripe integration via Cashier
4. PayPal integration
5. Webhook handlers **with signature verification and idempotency**
6. Order confirmation, invoices (PDF), refunds
7. Subscription create / upgrade / downgrade / cancel / grace period
8. Coupon application

> **Warning:** webhooks are where double-charging bugs live. The `webhook_events`
> table already has a unique constraint on `(provider, event_id)` to make replays
> a no-op. Do not bypass it.

## Phase B — Money out — 15%
9. Contributor earnings accrual on sale and on subscription download
10. Ledger writes (append-only, never update a balance in place)
11. Balance recalculation from the ledger
12. Payout request → approval → completion
13. Tax withholding, minimum threshold, holding period

> **Test this hardest.** It is the only part of the system where a bug moves real
> money to the wrong person. Write the tests before the implementation.

## Phase C — Dashboards — 25%
14. Customer dashboard (26 pages in brief §16)
15. Contributor dashboard (27 pages in §19) — upload and list exist, rest do not
16. Team accounts, invitations, seats, roles (§18)
17. Full admin dashboard and management modules (§24, §25)

## Phase D — Engagement — 10%
18. Likes and collections *(the buttons on every asset card are currently inert)*
19. Reviews and ratings
20. Notifications (in-app and email)
21. Support tickets

## Phase E — Content & growth — 10%
22. 24 email templates plus email logs UI
23. Blog, CMS pages, menu management
24. ~~SEO admin, sitemaps, robots.txt~~ — **done**; redirect manager and 404 monitoring remain
25. AEO — FAQ schema, answer sections
26. Analytics dashboards and reports

## Phase F — Editors & polish — 15%
27. SVG editor (§12)
28. Lottie editor (§13)
29. 3D viewer via Three.js (§14)
30. Remaining animation work (§4)
31. Accessibility audit, performance pass, deployment

---

# PART 4 — Guidance

## 4.1 Running the project

MariaDB is **not** installed as a Windows service, so it must be started manually
after each reboot:

```powershell
C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --console
```

Then, from `C:\xampp\htdocs\Local Server\iconors`:

```powershell
C:\xampp\php\php.exe artisan serve --port=8123
npm run dev          # or: npm run build
```

Open http://127.0.0.1:8123

**Nothing is on PATH.** Always use `C:\xampp\php\php.exe`, not `php`.

## 4.2 Demo accounts

```powershell
C:\xampp\php\php.exe artisan db:seed --class=DemoUsersSeeder
```

| Email | Role | Password |
|---|---|---|
| admin@iconors.test | Super Administrator | password |
| reviewer@iconors.test | Asset Reviewer | password |
| maker@iconors.test | Approved Contributor | password |
| applicant@iconors.test | Pending application | password |

Local only. The seeder refuses to run in production and is deliberately not
registered in `DatabaseSeeder`.

## 4.3 Importing the live iconors.com catalogue

```powershell
C:\xampp\php\php.exe artisan iconors:import              # everything
C:\xampp\php\php.exe artisan iconors:import --type=svg   # icons only (fast)
C:\xampp\php\php.exe artisan iconors:import --limit=50   # sample
C:\xampp\php\php.exe artisan iconors:import --fresh      # wipe previous import first
```

Safe to re-run: each asset records its source URL, and existing ones are skipped rather
than duplicated.

**What it pulls.** iconors.com is WordPress + Easy Digital Downloads. The library is
reachable without credentials through the WP REST API — roughly 689 media items
(251 SVG, 224 Lottie JSON, 181 PNG) plus a custom `lottie_animation` post type with 195
posts across six real categories.

**Naming.** The site's SVG filenames are descriptive (`Weather`, `Wifi-Icon`) and are used
directly. Its Lottie filenames are bare numbers (`4-2.json`), so those are named from the
category their post actually belongs to — otherwise you get 195 assets all called
"Lottie Animation".

**What it deliberately skips**
- WordPress resize derivatives (`icon-150x150.png`) — duplicates at worse resolution
- Site chrome: logos, favicons, banners, backgrounds
- JSON files that are not Bodymovin exports (checked for `v` + `layers`)

**Every imported SVG is sanitised** on the way in, using the same allowlist as contributor
uploads. These are files from an external server that will render on your pages.

### What it CANNOT import

The paid EDD download files are behind purchase authentication — correctly so. The
importer can take titles, descriptions, categories, prices and preview files, but **not
the protected source files**. Moving those needs a WordPress-side export or admin access
to that site. There were only 15 EDD products at the time of writing and they read as
test content; the media library is the real catalogue.

## 4.4 Common commands

```powershell
C:\xampp\php\php.exe artisan test                    # full suite
C:\xampp\php\php.exe artisan migrate                 # apply new migrations
C:\xampp\php\php.exe artisan db:seed                 # roles, settings, demo catalogue
C:\xampp\php\php.exe artisan optimize:clear          # clear all caches
```

Use `artisan test`, **not** `vendor/bin/phpunit` — the Windows wrapper swallows arguments.

## 4.5 Rules to keep following

These are not style preferences. Each exists because breaking it causes a specific,
expensive failure.

1. **Never gate an admin screen on a `*.view` permission.** Contributors hold
   `assets.view` for their own work. Gating the moderation queue on it leaked every
   contributor's unpublished submissions. Moderation screens gate on moderation
   permissions. *This bug was found by probing routes as each role — do that for
   every new admin page.*

2. **Money is `DECIMAL`, never float.** `0.1 + 0.2 !== 0.3` in binary floating point,
   and the error compounds across royalty splits until the ledger stops balancing.

3. **Ledger entries are never updated or deleted.** Corrections are new offsetting
   rows. A corrupted counter is recoverable; a mutated financial history is not.

4. **Sanitise on the way in, not on the way out.** If you clean SVG at render time,
   every future read path has to remember. One that forgets is an XSS.

5. **Freeze prices and commission at purchase time.** Changing a global rate later
   must never retroactively alter what a contributor already earned.

6. **Signed URLs must be bound to a user.** Otherwise they are bearer tokens — paste
   one in a forum and the premium file is public until it expires.

7. **Verify webhook signatures and enforce idempotency.** A replayed event must be a
   no-op, not a second payout.

8. **Never write `@php` or `@json` inside a Blade comment.** Blade extracts raw PHP
   blocks before stripping comments, so the comment loses its terminator and silently
   eats the rest of the template. The error message points nowhere near the cause.

9. **Hiding a button is not security.** Every action re-checks its permission or
   policy server-side.

## 4.6 Suggested order

If you continue with help, this order minimises rework:

1. **Likes and collections** — small, self-contained, makes every asset card work
2. **Earnings and ledger** — do this *before* checkout, so payments have somewhere
   correct to write to
3. **Cart and checkout** — once Stripe keys arrive
4. **Customer dashboard** — needs orders to exist first
5. **Contributor payouts** — needs the ledger
6. **Everything else** — largely independent after that

The reason earnings comes before checkout: if the ledger design has a flaw, it is far
cheaper to find it before money is flowing through it than after.

## 4.7 Hosting

| Feature | Shared / cPanel | VPS |
|---|---|---|
| Browse, search, auth, cart | ✅ | ✅ |
| Payments and webhooks | ✅ | ✅ |
| Queue worker | ⚠️ cron-driven | ✅ Supervisor |
| Redis | ❌ | ✅ |
| Meilisearch | ❌ | ✅ |
| Large uploads, preview generation | ⚠️ limited | ✅ |

**Recommendation: a VPS** (Hostinger, DigitalOcean, or Laravel Forge — roughly
$6–12/month). Shared hosting will work for browsing and payments but not for search,
queues or asset processing.

Everything infrastructural sits behind a driver interface, so moving from
`database`/`local` drivers to Redis/Meilisearch/S3 is an `.env` change, not a rewrite.

---

# PART 5 — Known Gaps

Honest list of things that look finished but are not:

1. **Like and save buttons on asset cards do nothing.** Styled, wired to no endpoint.
2. **The customer dashboard is a placeholder.** One page, not the 26 in the brief.
3. **Contributor-uploaded binaries (PNG, GLB, ZIP) stay `scan_status = pending`**, so they
   cannot be downloaded. Only SVG completes end to end through the upload path. A real
   malware scanner (ClamAV) must be wired in. *(Imported assets are marked clean because
   they come from your own site, not from an untrusted uploader.)*
4. **Preview generation only works for SVG and Lottie.** Raster and 3D formats need
   Intervention Image and a queue worker.
5. **No email is actually sent** — the `log` driver is active until SMTP is configured.
6. **Search uses database LIKE**, not Meilisearch. Fine to a few thousand assets;
   slow beyond that.
7. **No 2FA, no social login** despite being in brief §15.
8. **Imported Lottie titles are category-derived** (`Hand Gesture Animation 07`), because
   the source filenames are bare numbers. Imported SVG titles are the real names. If you
   want better Lottie names, they have to be set on the source site or edited here.
9. **The paid EDD source files were not imported** — they sit behind purchase auth on
   iconors.com. Only metadata and preview files came across. See §4.3.

None of these are hidden behind a "done" label anywhere in the code.
