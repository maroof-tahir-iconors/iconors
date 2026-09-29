<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contributor earnings and balances — brief §19, §23.
 *
 * Balances are DERIVED by summing `contributor_earnings`, never read from
 * a mutable running total. `contributor_balances` is a cache of that sum
 * and can be rebuilt at any time; if the two ever disagree, the earnings
 * rows are right.
 *
 * Rates come from settings, so an operator changes them without a deploy.
 * Crucially, the rate is FROZEN onto each earning row at the moment it is
 * created — changing the global rate later cannot alter what someone has
 * already earned.
 */
class EarningsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /** Commission percentage the CONTRIBUTOR receives for a given source. */
    public function rateFor(string $source, ?Contributor $contributor = null): float
    {
        // A per-contributor override wins over the platform default —
        // that is how a negotiated rate is honoured.
        if ($contributor?->commission_rate !== null) {
            return (float) $contributor->commission_rate;
        }

        return (float) match ($source) {
            'individual_sale', 'pack_sale' => $this->settings->get('commission_rate_one_time', 50),
            'subscription_download'        => $this->settings->get('commission_rate_subscription', 30),
            'api_download'                 => $this->settings->get('commission_rate_api', 25),
            default                        => 0,
        };
    }

    /**
     * Records an earning and its ledger entry in one transaction.
     *
     * A payout that exists without a ledger row — or vice versa — is a
     * discrepancy nobody notices until a contributor disputes their
     * balance, so the two are never written separately.
     */
    public function record(
        Contributor $contributor,
        string $source,
        float $grossAmount,
        ?int $assetId = null,
        ?int $orderItemId = null,
        ?int $downloadId = null,
    ): void {
        $rate = $this->rateFor($source, $contributor);
        $net  = round($grossAmount * ($rate / 100), 4);

        if ($net <= 0) {
            return;
        }

        $holdingDays = (int) $this->settings->get('payout_holding_days', 30);

        DB::transaction(function () use ($contributor, $source, $grossAmount, $rate, $net, $assetId, $orderItemId, $downloadId, $holdingDays) {
            $earningId = DB::table('contributor_earnings')->insertGetId([
                'contributor_id'  => $contributor->id,
                'asset_id'        => $assetId,
                'order_item_id'   => $orderItemId,
                'download_id'     => $downloadId,
                'source'          => $source,
                'gross_amount'    => $grossAmount,
                // Frozen here on purpose — see the class docblock.
                'commission_rate' => $rate,
                'net_amount'      => $net,
                'currency'        => $this->settings->get('default_currency', 'USD'),
                'status'          => 'pending',
                'available_at'    => now()->addDays($holdingDays),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            DB::table('ledger_entries')->insert([
                'reference'     => 'ERN-'.Str::upper(Str::random(12)),
                'type'          => $source === 'subscription_download' ? 'subscription_share' : 'sale',
                'direction'     => 'credit',
                'amount'        => $net,
                'currency'      => $this->settings->get('default_currency', 'USD'),
                'owner_type'    => Contributor::class,
                'owner_id'      => $contributor->id,
                'source_type'   => 'contributor_earnings',
                'source_id'     => $earningId,
                'balance_after' => $this->lifetimeTotal($contributor) + $net,
                'description'   => Str::headline($source).' earning',
                'created_at'    => now(),
            ]);

            $this->refreshBalance($contributor);
        });
    }

    /**
     * Rebuilds the cached balance row from the earnings rows.
     *
     * Deliberately a full recomputation rather than an increment: an
     * increment that runs twice is silently wrong forever, whereas this
     * is idempotent and self-healing.
     */
    public function refreshBalance(Contributor $contributor): void
    {
        $totals = DB::table('contributor_earnings')
            ->where('contributor_id', $contributor->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'pending'   THEN net_amount ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = 'available' THEN net_amount ELSE 0 END), 0) AS available,
                COALESCE(SUM(CASE WHEN status = 'paid'      THEN net_amount ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN status <> 'reversed' THEN net_amount ELSE 0 END), 0) AS lifetime
            ")
            ->first();

        DB::table('contributor_balances')->updateOrInsert(
            ['contributor_id' => $contributor->id],
            [
                'pending_balance'      => $totals->pending,
                'available_balance'    => $totals->available,
                'paid_balance'         => $totals->paid,
                'lifetime_earnings'    => $totals->lifetime,
                'currency'             => $this->settings->get('default_currency', 'USD'),
                'last_recalculated_at' => now(),
                'updated_at'           => now(),
                'created_at'           => now(),
            ],
        );
    }

    /**
     * Moves earnings past their holding period from pending to available.
     * Run on a schedule; safe to run repeatedly.
     */
    public function releaseMatured(): int
    {
        $released = DB::table('contributor_earnings')
            ->where('status', 'pending')
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->update(['status' => 'available', 'updated_at' => now()]);

        if ($released > 0) {
            foreach (Contributor::whereIn('id',
                DB::table('contributor_earnings')->distinct()->pluck('contributor_id')
            )->get() as $contributor) {
                $this->refreshBalance($contributor);
            }
        }

        return $released;
    }

    /** Full picture for the contributor dashboard. */
    public function summaryFor(Contributor $contributor): array
    {
        $balance = DB::table('contributor_balances')
            ->where('contributor_id', $contributor->id)
            ->first();

        $bySource = DB::table('contributor_earnings')
            ->where('contributor_id', $contributor->id)
            ->where('status', '!=', 'reversed')
            ->selectRaw('source, COUNT(*) AS entries, SUM(net_amount) AS total')
            ->groupBy('source')
            ->get();

        $minimum = (float) $this->settings->get('payout_minimum', 50);
        $available = (float) ($balance->available_balance ?? 0);

        return [
            'pending'    => (float) ($balance->pending_balance ?? 0),
            'available'  => $available,
            'paid'       => (float) ($balance->paid_balance ?? 0),
            'lifetime'   => (float) ($balance->lifetime_earnings ?? 0),
            'currency'   => $balance->currency ?? $this->settings->get('default_currency', 'USD'),
            'bySource'   => $bySource,
            'minimum'    => $minimum,
            'canWithdraw'=> $available >= $minimum && $minimum > 0,
            // Shown so the contributor knows exactly how far off they are
            // rather than just seeing a disabled button.
            'shortfall'  => max(0, $minimum - $available),
            'holdingDays'=> (int) $this->settings->get('payout_holding_days', 30),
            'rates'      => [
                'one_time'     => $this->rateFor('individual_sale', $contributor),
                'subscription' => $this->rateFor('subscription_download', $contributor),
                'api'          => $this->rateFor('api_download', $contributor),
            ],
        ];
    }

    private function lifetimeTotal(Contributor $contributor): float
    {
        return (float) DB::table('contributor_earnings')
            ->where('contributor_id', $contributor->id)
            ->where('status', '!=', 'reversed')
            ->sum('net_amount');
    }
}
