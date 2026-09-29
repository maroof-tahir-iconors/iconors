<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Contributor;
use App\Models\ContributorApplication;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin overview — brief §24.
 *
 * Every figure is a real query. Where a subsystem is not built yet the
 * card says so explicitly rather than rendering a plausible-looking zero:
 * a dashboard that invents numbers is worse than one that admits gaps,
 * because nobody can tell which figures to trust.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings)
    {
        // Staff-exclusive permissions only. `assets.view` and `analytics.view`
        // are deliberately absent — contributors hold both for their own work,
        // and this page aggregates platform-wide revenue and user data.
        abort_unless($request->user()?->hasAnyPermission([
            'settings.manage', 'users.view', 'assets.approve', 'contributors.view',
            'payments.manage', 'payouts.manage', 'reports.financial.view',
        ]), 403);

        $now = now();

        return view('admin.dashboard', [
            'revenue'      => $this->revenue(),
            'catalogue'    => $this->catalogue(),
            'people'       => $this->people($now),
            'queues'       => $this->queues(),
            'topAssets'    => $this->topAssets(),
            'topSearches'  => $this->topSearches(),
            'zeroResults'  => $this->zeroResults(),
            'topCategories'=> $this->topCategories(),
            'recentAudit'  => $this->recentAudit(),
            'health'       => $this->health($settings),
        ]);
    }

    private function revenue(): array
    {
        $paid = DB::table('orders')->whereIn('status', ['paid', 'partially_refunded'])->whereNull('deleted_at');

        return [
            'gross'          => (float) (clone $paid)->sum('total'),
            'refunded'       => (float) (clone $paid)->sum('refunded_total'),
            'orders'         => (clone $paid)->count(),
            'pendingPayouts' => (float) DB::table('payout_requests')
                ->whereIn('status', ['requested', 'under_review', 'approved'])
                ->sum('net_amount'),
            'contributorOwed' => (float) DB::table('contributor_balances')->sum('available_balance'),
            // Checkout is not built yet, so an empty revenue panel is
            // expected rather than alarming. Say so on the card.
            'live'           => DB::table('orders')->exists(),
        ];
    }

    private function catalogue(): array
    {
        $byStatus = Asset::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'published'  => (int) ($byStatus['published'] ?? 0),
            'draft'      => (int) ($byStatus['draft'] ?? 0),
            'total'      => (int) $byStatus->sum(),
            'downloads'  => (int) DB::table('downloads')->count(),
            'byStatus'   => $byStatus,
            'packs'      => DB::table('packs')->whereNull('deleted_at')->count(),
            'categories' => DB::table('categories')->whereNull('deleted_at')->count(),
        ];
    }

    private function people(\DateTimeInterface $now): array
    {
        return [
            'users'          => User::count(),
            'newThisWeek'    => User::where('created_at', '>=', $now->copy()->subWeek())->count(),
            'contributors'   => Contributor::where('status', 'approved')->count(),
            'teams'          => DB::table('teams')->whereNull('deleted_at')->count(),
            'subscriptions'  => DB::table('subscriptions')->whereIn('stripe_status', ['active', 'trialing'])->count(),
        ];
    }

    /** Everything waiting on a human. This is the dashboard's real job. */
    private function queues(): array
    {
        return [
            'assetsAwaitingReview' => Asset::whereIn('status', ['submitted', 'under_review'])->count(),
            'applications'         => ContributorApplication::whereIn('status', ['pending', 'under_review'])->count(),
            'openTickets'          => DB::table('tickets')->whereIn('status', ['open', 'pending'])->whereNull('deleted_at')->count(),
            'openReports'          => DB::table('reports')->where('status', 'open')->count(),
            'copyrightClaims'      => DB::table('copyright_claims')->whereIn('status', ['received', 'under_review'])->count(),
            'payoutRequests'       => DB::table('payout_requests')->whereIn('status', ['requested', 'under_review'])->count(),
        ];
    }

    private function topAssets()
    {
        return Asset::published()
            ->with('contributor:id,display_name')
            ->orderByDesc('downloads_count')
            ->limit(8)
            ->get(['id', 'title', 'slug', 'contributor_id', 'downloads_count', 'views_count', 'is_premium']);
    }

    private function topSearches()
    {
        return DB::table('search_queries')
            ->selectRaw('query, COUNT(*) as searches, MAX(results_count) as results')
            ->where('is_zero_result', false)
            ->groupBy('query')
            ->orderByDesc('searches')
            ->limit(8)
            ->get();
    }

    /**
     * Zero-result searches are the highest-signal input to the catalogue
     * roadmap — they are literal demand the marketplace failed to meet.
     */
    private function zeroResults()
    {
        return DB::table('search_queries')
            ->selectRaw('query, COUNT(*) as searches')
            ->where('is_zero_result', true)
            ->groupBy('query')
            ->orderByDesc('searches')
            ->limit(8)
            ->get();
    }

    private function topCategories()
    {
        return DB::table('categories')
            ->whereNull('deleted_at')
            ->where('assets_count', '>', 0)
            ->orderByDesc('assets_count')
            ->limit(8)
            ->get(['name', 'slug', 'assets_count']);
    }

    private function recentAudit()
    {
        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->orderByDesc('audit_logs.id')
            ->limit(10)
            ->get([
                'audit_logs.action', 'audit_logs.target_type', 'audit_logs.target_id',
                'audit_logs.created_at', 'audit_logs.actor_label',
                'users.name as actor_name',
            ]);
    }

    /**
     * Configuration gaps that will bite in production. Surfaced here so
     * they are visible before a customer discovers them at checkout.
     */
    private function health(SettingsService $settings): array
    {
        $checks = [];

        $gatewayLive = $settings->get('stripe_enabled') || $settings->get('paypal_enabled');
        $checks[] = [
            'label'  => 'Payment gateway',
            'ok'     => (bool) $gatewayLive,
            'detail' => $gatewayLive ? 'At least one gateway is enabled.' : 'No gateway enabled — checkout cannot take money.',
            'route'  => 'admin.settings.payments',
        ];

        $mailDriver = $settings->get('mail_driver', 'log');
        $checks[] = [
            'label'  => 'Email delivery',
            'ok'     => $mailDriver !== 'log',
            'detail' => $mailDriver === 'log'
                ? 'Driver is "log" — emails are written to the log, not sent.'
                : "Sending via {$mailDriver}.",
            'route'  => 'admin.settings.mail',
        ];

        $plans = DB::table('plans')->where('is_active', true)->whereNull('deleted_at')->count();
        $checks[] = [
            'label'  => 'Subscription plans',
            'ok'     => $plans > 0,
            'detail' => $plans > 0 ? "{$plans} active ".str('plan')->plural($plans) : 'No active plans — nobody can subscribe.',
            'route'  => 'admin.plans.index',
        ];

        $licenses = DB::table('licenses')->where('is_active', true)->count();
        $checks[] = [
            'label'  => 'Licences',
            'ok'     => $licenses > 0,
            'detail' => $licenses > 0 ? "{$licenses} active" : 'No active licences — downloads have no terms attached.',
            'route'  => 'admin.licenses.index',
        ];

        $unscanned = DB::table('asset_files')->where('scan_status', 'pending')->count();
        $checks[] = [
            'label'  => 'File scanning',
            'ok'     => $unscanned === 0,
            'detail' => $unscanned === 0
                ? 'All files cleared.'
                : "{$unscanned} ".str('file')->plural($unscanned).' awaiting a scan and cannot be downloaded.',
            'route'  => null,
        ];

        return [
            'checks'  => $checks,
            'storage' => $this->storageUsage(),
        ];
    }

    private function storageUsage(): array
    {
        $sum = static function (string $disk): int {
            try {
                $bytes = 0;
                foreach (Storage::disk($disk)->allFiles() as $file) {
                    $bytes += Storage::disk($disk)->size($file);
                }

                return $bytes;
            } catch (\Throwable) {
                // A misconfigured or remote disk must not break the dashboard.
                return 0;
            }
        };

        return [
            'private' => $sum('private'),
            'public'  => $sum('public'),
        ];
    }
}
