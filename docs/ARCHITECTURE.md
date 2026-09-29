# Iconors — Technical Architecture

Status: **Phase 1 in progress.** Environment verified and running (see §17).

---

## 1. Stack (verified on this machine, not assumed)

| Layer | Choice | Verified |
|---|---|---|
| PHP | 8.2.12 (XAMPP, ZTS x64) | ✅ `php -v` |
| Framework | Laravel 12.64.0 | ✅ `artisan --version` |
| Database | MariaDB 10.4.32 | ✅ `SELECT VERSION()` |
| Extensions | gd, zip, intl, sodium, pdo_mysql, mbstring, curl, openssl, exif, bcmath, fileinfo | ✅ enabled in php.ini |
| Composer | 2.10.2 (sha384-verified installer) | ✅ |
| Node | 24.18.0 / npm 11.16.0 | ✅ |
| Frontend | Bootstrap 5.3 + SCSS + Vite + jQuery | pending |
| Search | Scout — **database driver in dev**, Meilisearch in prod | see §14 |
| Queue/Cache | **database driver in dev**, Redis in prod | see §16 |

### Deviations from the brief, and why

The brief specifies Redis, Meilisearch, S3 and Horizon. **None of these exist on this
Windows/XAMPP machine**, and Redis has no supported native Windows build. Rather than
pretend otherwise, every one of these is behind a **driver interface**, configured by env:

```
SCOUT_DRIVER=database   → meilisearch    (search)
QUEUE_CONNECTION=database → redis        (queues)
CACHE_STORE=database    → redis          (cache)
FILESYSTEM_DISK=local   → s3             (asset storage)
```

The application code never names a driver. This is what "keep search/storage/email
services modular" in §42 actually requires, and it means the same codebase runs on this
XAMPP box today and on a VPS unchanged. §17 lists precisely what degrades where.

---

## 2. Sitemap

**Public (54 routes)** — Home, Browse, and the nine asset-type landings (Icons, SVG, 3D,
Lottie, Animated, Illustrations, Stickers, Emojis, Free/Premium), Packs, Categories,
Styles, Trending, New, Contributors + profile, Asset detail, Pack detail, Search,
Pricing, Teams, Enterprise, Become-a-Contributor, API, Plugins, Blog + category +
article, Help, FAQ, Support, Contact, About, Careers, License, Terms, Privacy, Refund,
Copyright, the 5 auth screens, Cart, Checkout, Payment success/failure, 404, 500,
Maintenance.

**Authenticated** — `/dashboard/*` (26 customer pages), `/team/*`, `/contributor/*`
(27 pages), `/admin/*`.

---

## 3. Database Entity Plan

~70 tables in 12 domains. Money is **always** `DECIMAL(12,4)` — never float — and every
financial mutation writes an append-only ledger row.

1. **Identity** — users, profiles, sessions, devices, two_factor, social_accounts
2. **Authorization** — roles, permissions, model_has_* (Spatie)
3. **Teams** — teams, members, invitations, seats
4. **Contributors** — contributors, applications, specialties, payout_methods, tax_info
5. **Catalogue** — assets, asset_files, asset_previews, asset_versions, asset_types, packs, pack_assets
6. **Taxonomy** — categories, tags, styles, licenses, formats, software, colors, industries, use_cases
7. **Engagement** — likes, collections, collection_assets, recently_viewed, saved_searches, reviews, follows
8. **Commerce** — carts, cart_items, orders, order_items, payments, refunds, invoices, coupons, coupon_usage
9. **Subscriptions** — plans, plan_features, subscriptions, subscription_items
10. **Financial** — contributor_earnings, contributor_balances, ledger_entries, payout_requests, payout_transactions
11. **Ops** — downloads, download_licenses, notifications, tickets, ticket_messages, reports, copyright_claims
12. **Platform** — settings, pages, posts, menus, menu_items, seo_meta, redirects, email_templates, email_logs, analytics_events, audit_logs, webhook_events, api_keys

**Non-negotiable invariants**
- Premium source files live **outside** the web root; served only via signed, expiring URLs.
- `ledger_entries` is insert-only. No update or delete path exists in code — balances are
  derived by summation, never stored as a mutable running total.
- `audit_logs` has no admin-facing edit/delete route (brief §33).
- Every FK indexed; unique constraints on slugs, order numbers, coupon codes.

---

## 4. Role & Permission Matrix

19 roles per §26 across 4 tiers: **Platform** (Super Admin → Analyst, 12 roles),
**Contributor**, **Team** (Owner/Admin/Billing/Asset Manager/Member/Viewer),
**Customer** (Premium/Free/Suspended).

Enforcement is **three-layered and never relies on hidden buttons**:
1. Route middleware — coarse gate (`role:`, `permission:`)
2. Policy — per-record authority (owns it? team scope? published?)
3. Form Request — field-level (a contributor may not set `is_featured` or `commission_rate`)

Layer 3 is the one most often skipped, and is why "edit own" vs "edit all" is modelled as
distinct permissions rather than a runtime `if ($model->user_id === auth()->id())`.

---

## 5. Route Structure

```
routes/
  web.php          public marketplace
  auth.php         authentication
  dashboard.php    customer      → auth, verified
  team.php         team          → auth, team.member
  contributor.php  contributor   → auth, role:contributor, contributor.approved
  admin.php        admin         → auth, role:admin|super-admin
  api.php          v1, token-auth, rate-limited
  webhooks.php     NO csrf, signature-verified, idempotent
```

`webhooks.php` is deliberately separate: it must bypass CSRF while still being
authenticated by provider signature + replay-protected by an idempotency key stored in
`webhook_events`. Mixing it into `api.php` is how double-charging bugs happen.

---

## 6. Payment Workflow

```
Cart → Checkout → PaymentGateway (interface)
                    ├─ StripeGateway
                    ├─ PayPalGateway
                    └─ BankTransferGateway
                  ↓
       Provider redirect / intent
                  ↓
       Webhook → verify signature → check idempotency key
                  ↓ (first time only)
       DB::transaction:
         order.paid → invoice → license grant
         → contributor earnings → ledger rows
                  ↓
       Queued: email + notification
```

The entire fulfilment block is one transaction so a mid-flight failure cannot produce a
paid order with no license or an earning with no ledger entry. Emails are queued
*outside* it — a mail outage must never roll back a successful payment.

---

## 7. Subscription Workflow

Cashier for Stripe. Plans and features are DB rows (§42: no hard-coded pricing).
Entitlement is resolved through a single `SubscriptionService::can($user, $ability)`
so download limits, source-file access, editor access and API access all answer from one
place. Grace periods honour `ends_at`, not `cancelled_at`.

## 8. Contributor Workflow

Apply → Pending → Under Review → (More Info) → Approved → upload rights granted.
Rejection and suspension are reversible states, not deletions.

## 9. Asset Review Workflow

Draft → Uploading → Processing → Validation Failed | Submitted → Under Review →
Changes Requested | Approved → Scheduled | Published → Suspended | Copyright Review |
Archived.

Only `Published` enters the search index. Indexing is an event listener on the status
transition, not a cron sweep, so the marketplace never shows an unapproved asset.

## 10. Secure Download Workflow

```
Request → authorize (purchase | subscription | free-tier)
        → check download quota
        → SignedUrlService: short-TTL signed URL
        → stream from private disk (never a public path)
        → record download + license + analytics
        → increment contributor earnings (queued)
```

Premium files are stored on a private disk with no public route. The signed URL is the
only access path and expires. This is the single most security-critical flow in the
platform — it is where a marketplace leaks its entire inventory if done lazily.

## 11. Search Architecture

Scout, driver-swapped. Indexed: title, description, tags, category, style, contributor,
pack, formats, software, industries, use-cases. Filters are URL query params so results
are shareable (§9). Zero-result queries are logged — they are the highest-signal input to
the catalogue roadmap.

## 12. Animation Strategy

Tiered, and **loaded per-page, never globally**:

| Tier | Tech | Loaded |
|---|---|---|
| 1 | CSS transitions/keyframes | always (cheap) |
| 2 | GSAP + ScrollTrigger | pages with scroll reveals |
| 3 | Lottie Web | lazy, on viewport intersection |
| 4 | Three.js | **only** on pages with a 3D asset, dynamic `import()` |

Gated by `prefers-reduced-motion`, `navigator.hardwareConcurrency`, and viewport width.
Per §34, dashboards and checkout load no tier-3/4 bundle at all.

## 13. Folder Structure

Standard Laravel 12, plus:
```
app/Services/{Payment,Storage,Search,Mail,Asset,Royalty}/   ← swappable drivers
app/Support/Settings/                                        ← dynamic branding
resources/scss/                                              ← tokens + Bootstrap override
resources/js/modules/                                        ← one module per behaviour
```

## 14. Dynamic Branding

`settings` table → `SettingsService` (cached) → Blade injects `:root{}` overrides after
the compiled stylesheet. Because all components consume `var(--ico-*)`, changing a colour
in admin restyles every page with no rebuild. Defaults are in `resources/scss/_tokens.scss`;
extraction evidence in `docs/BRAND.md`.

## 15. Testing

Pest. Priority order — download authorization, payment webhooks (incl. replay), permission
enforcement, subscription entitlement, royalty arithmetic. These are the flows where a bug
costs money or leaks inventory.

---

## 16. Hosting Requirements — honest assessment

| Feature | Shared/cPanel | VPS |
|---|---|---|
| Browse, search (DB driver), auth, cart | ✅ | ✅ |
| Payments + webhooks | ✅ | ✅ |
| Queue worker (Supervisor) | ⚠️ cron-driven `queue:work --stop-when-empty` | ✅ |
| Redis cache/queue/session | ❌ | ✅ |
| Meilisearch / Typesense | ❌ | ✅ |
| Horizon | ❌ | ✅ |
| Large/chunked uploads | ⚠️ PHP limits | ✅ |
| Preview generation (image/3D) | ⚠️ CPU-limited | ✅ |
| S3 + CDN delivery | ✅ (external) | ✅ |

**Verdict:** functional on cPanel with DB-driven queue/cache/search; Meilisearch, Redis
and Horizon genuinely require VPS-level hosting. This is stated plainly per the brief's
request rather than glossed over.

## 17. Local environment — current state

- MariaDB running on 127.0.0.1:3306; database `iconors` created (utf8mb4/unicode_ci)
- Laravel 12.64.0 installed; `APP_KEY` generated; base migrations applied
- `php.ini` backed up to `php.ini.backup-iconors` before extensions were enabled
- No Redis / Meilisearch locally → dev uses `database` drivers throughout

## 18. Phased Plan

Phases follow the brief's §41 order. Phase 1 (foundation, schema, auth, roles, branding)
is underway; the remainder proceed in sequence.
