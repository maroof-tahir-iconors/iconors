<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Contributor\ApplicationController as ContributorApplicationController;
use App\Http\Controllers\Contributor\AssetController as ContributorAssetController;
use App\Http\Controllers\Contributor\DashboardController as ContributorDashboardController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketplace
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

/*
 * Technical SEO. Generated from live data so a newly published asset is
 * discoverable without anyone regenerating a static file.
 */
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('seo.sitemap.index');
Route::get('/sitemap-{type}.xml', [SeoController::class, 'sitemap'])
    ->where('type', 'pages|assets|categories|contributors')
    ->name('seo.sitemap');

Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
Route::get('/assets/{slug}', [AssetController::class, 'show'])->name('assets.show');

/*
|--------------------------------------------------------------------------
| Marketing & content
|--------------------------------------------------------------------------
| Pricing renders live plan rows; the editorial pages come from the `pages`
| table so an administrator can rewrite them without a deploy.
*/
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/careers', [PageController::class, 'careers'])->name('careers');
Route::get('/blog', [PageController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [PageController::class, 'post'])->name('blog.post');

// Catch-all CMS page. Registered LAST among public GETs so it can never
// shadow a real route — `/pricing` must hit the controller above, not this.
Route::get('/p/{slug}', [PageController::class, 'show'])->name('page');

// Typeahead. Throttled because it runs a LIKE on every keystroke and is
// the cheapest endpoint on the site to abuse.
Route::get('/api/search/suggest', [AssetController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('search.suggest');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Secure downloads
|--------------------------------------------------------------------------
| Two steps by design. `download.request` authorises and mints a signed
| URL; `download.serve` validates the signature and streams the bytes.
|
| Splitting them means the URL that actually carries the file is
| short-lived and single-purpose — the asset page never contains a
| working download link in its HTML, so scraping the page yields nothing.
*/
Route::middleware('auth')->group(function () {
    Route::post('/download/{asset}', [DownloadController::class, 'request'])
        ->middleware('throttle:30,1')
        ->name('download.request');

    Route::get('/download/{asset}/file/{file}', [DownloadController::class, 'serve'])
        ->middleware('signed')
        ->name('download.serve');

    // Renders the account area's Overview tab. The path and name stay
    // `dashboard` because the login, registration and stop-impersonating
    // redirects all target it.
    Route::get('/dashboard', [AccountController::class, 'overview'])->name('dashboard');

    // Reachable WHILE impersonating, so it cannot live behind an admin gate.
    Route::post('/stop-impersonating', [AdminUserController::class, 'stopImpersonating'])->name('impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| My Account
|--------------------------------------------------------------------------
| The customer-facing account area. `/account` redirects to the collections
| tab so the bare URL is never a dead end, and each tab keeps its own route
| name so deep links and the active-tab highlight work.
*/
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::redirect('/', '/account/collections');

    Route::get('/collections', [AccountController::class, 'collections'])->name('collections');
    Route::post('/collections', [AccountController::class, 'storeCollection'])->name('collections.store');
    Route::delete('/collections/{collection}', [AccountController::class, 'destroyCollection'])
        ->name('collections.destroy');

    Route::get('/downloads', [AccountController::class, 'downloads'])->name('downloads');

    Route::get('/following', [AccountController::class, 'following'])->name('following');
    Route::delete('/following/{contributor}', [AccountController::class, 'unfollow'])->name('following.destroy');

    Route::get('/api', [AccountController::class, 'api'])->name('api');

    Route::get('/details', [AccountController::class, 'details'])->name('details');
    Route::put('/details', [AccountController::class, 'updateDetails'])->name('details.update');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');

    Route::get('/subscription', [AccountController::class, 'subscription'])->name('subscription');

    Route::get('/members', [AccountController::class, 'members'])->name('members');
});

/*
|--------------------------------------------------------------------------
| Contributor
|--------------------------------------------------------------------------
| Applying is open to any authenticated user; the contributor workspace
| additionally requires an APPROVED contributor record. The middleware
| checks contributor status rather than only the role, so suspension takes
| effect immediately without an admin also having to strip roles.
*/
Route::middleware('auth')->prefix('contributor')->name('contributor.')->group(function () {
    Route::get('/apply', [ContributorApplicationController::class, 'create'])->name('apply');
    Route::post('/apply', [ContributorApplicationController::class, 'store'])->name('apply.store');
    Route::get('/application', [ContributorApplicationController::class, 'status'])->name('apply.status');

    Route::middleware('contributor.approved')->group(function () {
        Route::get('/', [ContributorDashboardController::class, 'overview'])->name('dashboard');

        Route::get('/assets', [ContributorAssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/upload', [ContributorAssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [ContributorAssetController::class, 'store'])->name('assets.store');

        Route::get('/analytics', [ContributorDashboardController::class, 'analytics'])->name('analytics');

        Route::get('/earnings', [ContributorDashboardController::class, 'earnings'])->name('earnings');
        Route::post('/earnings/payout', [ContributorDashboardController::class, 'requestPayout'])->name('payout.request');

        Route::get('/profile', [ContributorDashboardController::class, 'profile'])->name('profile');
        Route::post('/profile', [ContributorDashboardController::class, 'updateProfile'])->name('profile.update');
    });
});

/*
|--------------------------------------------------------------------------
| Admin moderation
|--------------------------------------------------------------------------
| The permission middleware is a coarse gate only; each action re-checks
| its own permission or policy server-side (brief §26).
*/
// NOTE: the gate here is assets.approve, NOT assets.view. Contributors
// legitimately hold assets.view (to see their own work), so gating on it
// let them load the moderation queue and read other contributors'
// unpublished submissions. They could not act — the policy blocked that —
// but the listing itself was a disclosure. Moderation screens must be
// gated on a moderation permission.
Route::middleware(['auth', 'permission:assets.approve|contributors.view'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/applications', [ReviewController::class, 'applications'])->name('applications');
        Route::post('/applications/{application}/approve', [ReviewController::class, 'approveApplication'])->name('applications.approve');
        Route::post('/applications/{application}/reject', [ReviewController::class, 'rejectApplication'])->name('applications.reject');
        Route::post('/applications/{application}/info', [ReviewController::class, 'requestApplicationInfo'])->name('applications.info');

        Route::get('/assets/review', [ReviewController::class, 'assets'])->name('assets.review');

    });

/*
|--------------------------------------------------------------------------
| User administration (brief §25)
|--------------------------------------------------------------------------
| Its OWN group, gated on user-management permissions. A Support Manager
| holds `users.view` but not `assets.approve`, so folding these into the
| moderation group above would lock support staff out of the user records
| they need to do their job. Third time this pattern has come up: group
| routes by job function, never by URL prefix.
|
| Each action still re-checks its own specific permission in the controller.
*/
Route::middleware(['auth', 'permission:users.view|users.edit.all|roles.manage|users.impersonate'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');
        Route::post('/users/{user}/logout', [AdminUserController::class, 'forceLogout'])->name('users.logout');
        Route::post('/users/{user}/password-reset', [AdminUserController::class, 'forcePasswordReset'])->name('users.password');
        Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/assets/{asset}/approve', [ReviewController::class, 'approveAsset'])->name('assets.approve');
        Route::post('/assets/{asset}/changes', [ReviewController::class, 'requestAssetChanges'])->name('assets.changes');
        Route::post('/assets/{asset}/reject', [ReviewController::class, 'rejectAsset'])->name('assets.reject');
    });

/*
|--------------------------------------------------------------------------
| Admin settings
|--------------------------------------------------------------------------
| A SEPARATE group from moderation on purpose. A Finance Manager holds
| payments.manage but not assets.approve, so folding these into the
| moderation group locked finance staff out of the payment screens
| entirely. Permission groups should mirror job function, not URL prefix.
|
| Each action still re-checks its own specific permission in the
| controller — this gate is only the coarse outer door.
*/
/*
 * Dashboard home.
 *
 * Gated ONLY on staff-exclusive permissions. It must not include
 * `assets.view` or `analytics.view`: contributors hold both — the first to
 * see their own uploads, the second to see their own earnings — and gating
 * on them let a contributor load the overview and read platform revenue,
 * total user counts and every moderation queue. Same trap as the review
 * queue: a permission that is handed out widely is never an admin gate.
 */
Route::middleware(['auth', 'permission:settings.manage|users.view|assets.approve|contributors.view|payments.manage|payouts.manage|reports.financial.view'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
    });

Route::middleware(['auth', 'permission:payments.manage|smtp.manage|settings.manage|branding.manage|seo.manage|payouts.manage|subscriptions.edit.all|taxonomies.edit.all'])
    ->prefix('admin')->name('admin.')->group(function () {
        // Site identity and global content.
        Route::get('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
        Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');

        // Colours, typography, logos — brief §2's "change a colour globally".
        Route::get('/settings/branding', [SettingsController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');

        // Meta defaults, structured data, sitemaps, analytics.
        Route::get('/settings/seo', [SettingsController::class, 'seo'])->name('settings.seo');
        Route::post('/settings/seo', [SettingsController::class, 'updateSeo'])->name('settings.seo.update');

        Route::get('/settings/payments', [SettingsController::class, 'payments'])->name('settings.payments');
        Route::post('/settings/payments', [SettingsController::class, 'updatePayments'])->name('settings.payments.update');

        Route::get('/settings/mail', [SettingsController::class, 'mail'])->name('settings.mail');
        Route::post('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
        Route::post('/settings/mail/test', [SettingsController::class, 'testMail'])->name('settings.mail.test');

        // Commission rates, payout holding period, refund window.
        Route::get('/settings/royalties', [SettingsController::class, 'royalties'])->name('settings.royalties');
        Route::post('/settings/royalties', [SettingsController::class, 'updateRoyalties'])->name('settings.royalties.update');

        // Free download limit, upload cap, review policy, signed-URL TTL.
        Route::get('/settings/marketplace', [SettingsController::class, 'marketplace'])->name('settings.marketplace');
        Route::post('/settings/marketplace', [SettingsController::class, 'updateMarketplace'])->name('settings.marketplace.update');

        // Subscription plan pricing (brief §22 — nothing hard-coded).
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        // Licence terms wording.
        Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
        Route::get('/licenses/create', [LicenseController::class, 'create'])->name('licenses.create');
        Route::post('/licenses', [LicenseController::class, 'store'])->name('licenses.store');
        Route::get('/licenses/{license}/edit', [LicenseController::class, 'edit'])->name('licenses.edit');
        Route::put('/licenses/{license}', [LicenseController::class, 'update'])->name('licenses.update');
        Route::delete('/licenses/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');
    });
