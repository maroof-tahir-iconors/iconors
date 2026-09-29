<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Contributor\EarningsService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contributor overview, analytics, earnings and payouts — brief §19.
 *
 * Where a figure is genuinely zero because the underlying feature is not
 * built yet (checkout, for instance), the view says so rather than
 * rendering a bare "$0.00" that reads like a reporting failure.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly EarningsService $earnings,
        private readonly SettingsService $settings,
    ) {}

    public function overview(Request $request)
    {
        $contributor = $request->user()->contributor;

        return view('contributor.dashboard', [
            'contributor' => $contributor,
            'earnings'    => $this->earnings->summaryFor($contributor),
            'stats'       => $this->stats($contributor->id),
            'statusCounts'=> $this->statusCounts($contributor->id),
            'topAssets'   => $this->topAssets($contributor->id),
            'recent'      => $this->recentDownloads($contributor->id),
            'chart'       => $this->downloadsByDay($contributor->id),
            'needsAction' => $this->needsAction($contributor->id),
        ]);
    }

    public function earnings(Request $request)
    {
        $contributor = $request->user()->contributor;

        return view('contributor.earnings', [
            'contributor' => $contributor,
            'summary'     => $this->earnings->summaryFor($contributor),
            'entries'     => DB::table('contributor_earnings')
                ->leftJoin('assets', 'assets.id', '=', 'contributor_earnings.asset_id')
                ->where('contributor_earnings.contributor_id', $contributor->id)
                ->orderByDesc('contributor_earnings.id')
                ->paginate(25, [
                    'contributor_earnings.*',
                    'assets.title AS asset_title',
                    'assets.slug AS asset_slug',
                ]),
            'payouts' => DB::table('payout_requests')
                ->where('contributor_id', $contributor->id)
                ->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function analytics(Request $request)
    {
        $contributor = $request->user()->contributor;

        return view('contributor.analytics', [
            'contributor' => $contributor,
            'stats'       => $this->stats($contributor->id),
            'chart'       => $this->downloadsByDay($contributor->id, 30),
            'byType'      => DB::table('assets')
                ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
                ->where('assets.contributor_id', $contributor->id)
                ->whereNull('assets.deleted_at')
                ->selectRaw('asset_types.name AS type, COUNT(*) AS assets, SUM(assets.downloads_count) AS downloads, SUM(assets.views_count) AS views')
                ->groupBy('asset_types.name')
                ->orderByDesc('downloads')
                ->get(),
            'byCategory' => DB::table('assets')
                ->leftJoin('categories', 'categories.id', '=', 'assets.category_id')
                ->where('assets.contributor_id', $contributor->id)
                ->whereNull('assets.deleted_at')
                ->selectRaw('COALESCE(categories.name, "Uncategorised") AS category, COUNT(*) AS assets, SUM(assets.downloads_count) AS downloads')
                ->groupBy('category')
                ->orderByDesc('downloads')
                ->limit(10)
                ->get(),
            'topAssets' => $this->topAssets($contributor->id, 15),
        ]);
    }

    /**
     * Requests a payout of the available balance — brief §23.
     *
     * Only `available` earnings are eligible: pending ones are still
     * inside the holding period that protects against paying out on a
     * sale that is later refunded.
     */
    public function requestPayout(Request $request)
    {
        $contributor = $request->user()->contributor;
        $summary     = $this->earnings->summaryFor($contributor);

        if (! $summary['canWithdraw']) {
            return back()->withErrors([
                'payout' => $summary['shortfall'] > 0
                    ? 'You need '.number_format($summary['shortfall'], 2).' more in available balance '
                      .'to reach the '.number_format($summary['minimum'], 2).' minimum.'
                    : 'You have no available balance to withdraw.',
            ]);
        }

        // One open request at a time — otherwise a double-submit or an
        // impatient contributor could queue the same balance twice.
        $open = DB::table('payout_requests')
            ->where('contributor_id', $contributor->id)
            ->whereIn('status', ['requested', 'under_review', 'approved', 'processing'])
            ->exists();

        if ($open) {
            return back()->withErrors(['payout' => 'You already have a payout request in progress.']);
        }

        $validated = $request->validate([
            'payout_method_id' => ['nullable', 'exists:payout_methods,id'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $amount  = $summary['available'];
        $feeFlat = (float) $this->settings->get('payout_fee_fixed', 0);
        $feePct  = (float) $this->settings->get('payout_fee_percent', 0);
        $fee     = round($feeFlat + ($amount * $feePct / 100), 4);

        DB::transaction(function () use ($contributor, $amount, $fee, $validated) {
            $payoutId = DB::table('payout_requests')->insertGetId([
                'reference'        => 'PO-'.strtoupper(\Illuminate\Support\Str::random(10)),
                'contributor_id'   => $contributor->id,
                'payout_method_id' => $validated['payout_method_id'] ?? null,
                'amount'           => $amount,
                'fee'              => $fee,
                'tax_withheld'     => 0,
                'net_amount'       => max(0, $amount - $fee),
                'currency'         => $this->settings->get('default_currency', 'USD'),
                'status'           => 'requested',
                'notes'            => $validated['notes'] ?? null,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Claim the earnings so the same balance cannot be requested
            // again while this one is outstanding.
            DB::table('contributor_earnings')
                ->where('contributor_id', $contributor->id)
                ->where('status', 'available')
                ->update(['payout_request_id' => $payoutId, 'updated_at' => now()]);
        });

        return back()->with('status',
            'Payout of '.number_format($amount, 2).' requested. Finance will review it shortly.');
    }

    public function profile(Request $request)
    {
        return view('contributor.profile', [
            'contributor' => $request->user()->contributor,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $contributor = $request->user()->contributor;

        $validated = $request->validate([
            'display_name'  => ['required', 'string', 'max:120'],
            'biography'     => ['nullable', 'string', 'max:2000'],
            'country'       => ['nullable', 'string', 'size:2'],
            'website'       => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        // A contributor may edit their presentation, never their status,
        // commission rate or verification flags — those are staff-only and
        // are simply not in the validated set.
        $contributor->update($validated);

        return back()->with('status', 'Your profile has been updated.');
    }

    // ---------------------------------------------------------------

    private function stats(int $contributorId): array
    {
        $assets = DB::table('assets')
            ->where('contributor_id', $contributorId)
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*) AS total,
                COALESCE(SUM(views_count), 0) AS views,
                COALESCE(SUM(downloads_count), 0) AS downloads,
                COALESCE(SUM(likes_count), 0) AS likes
            ')
            ->first();

        $downloads = (int) $assets->downloads;
        $views     = (int) $assets->views;

        return [
            'assets'    => (int) $assets->total,
            'views'     => $views,
            'downloads' => $downloads,
            'likes'     => (int) $assets->likes,
            // Downloads per view. The single most useful number for a
            // contributor deciding what to make more of.
            'conversion' => $views > 0 ? round($downloads / $views * 100, 1) : 0.0,
            'last30'    => DB::table('downloads')
                ->where('contributor_id', $contributorId)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    private function statusCounts(int $contributorId)
    {
        return DB::table('assets')
            ->where('contributor_id', $contributorId)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    /** Submissions the contributor themselves must act on. */
    private function needsAction(int $contributorId): array
    {
        return [
            'changes_requested' => Asset::where('contributor_id', $contributorId)
                ->where('status', 'changes_requested')
                ->get(['id', 'title', 'slug', 'status_reason']),
            'drafts' => DB::table('assets')
                ->where('contributor_id', $contributorId)
                ->where('status', 'draft')
                ->whereNull('deleted_at')
                ->count(),
            'inReview' => DB::table('assets')
                ->where('contributor_id', $contributorId)
                ->whereIn('status', ['submitted', 'under_review'])
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    private function topAssets(int $contributorId, int $limit = 8)
    {
        return Asset::where('contributor_id', $contributorId)
            ->with('previews')
            ->orderByDesc('downloads_count')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'status', 'downloads_count', 'views_count', 'likes_count', 'is_premium', 'price']);
    }

    private function recentDownloads(int $contributorId)
    {
        return DB::table('downloads')
            ->join('assets', 'assets.id', '=', 'downloads.asset_id')
            ->where('downloads.contributor_id', $contributorId)
            ->orderByDesc('downloads.id')
            ->limit(10)
            ->get(['assets.title', 'assets.slug', 'downloads.access_type', 'downloads.created_at']);
    }

    /**
     * Daily download counts for a sparkline. Gaps are filled with zeroes
     * so the chart's x-axis is continuous — a series that silently skips
     * quiet days overstates how steady the trend is.
     */
    private function downloadsByDay(int $contributorId, int $days = 14): array
    {
        $rows = DB::table('downloads')
            ->where('contributor_id', $contributorId)
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $series[] = [
                'date'  => $date,
                'label' => now()->subDays($i)->format('j M'),
                'value' => (int) ($rows[$date] ?? 0),
            ];
        }

        return $series;
    }
}
