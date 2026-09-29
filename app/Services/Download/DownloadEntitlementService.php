<?php

namespace App\Services\Download;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;

/**
 * The single authority on "may this user download this file?".
 *
 * Every download route, API endpoint and queue job asks this class and
 * nothing else. Entitlement logic duplicated across controllers is how
 * a marketplace ends up with one path that checks the subscription and
 * one that forgot to — so there is exactly one path here.
 *
 * Brief §43: "Premium assets require a valid purchase or subscription."
 */
class DownloadEntitlementService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function check(?User $user, Asset $asset): DownloadEntitlement
    {
        // An unpublished asset is invisible regardless of entitlement.
        // Checked first so a direct-ID probe cannot confirm the existence
        // of an unreleased or suspended asset.
        if (! $this->isPublished($asset)) {
            return DownloadEntitlement::deny('asset_unavailable');
        }

        // --- Free assets ------------------------------------------------
        if (! $asset->is_premium && ! $asset->subscription_only) {
            if (! $user) {
                return DownloadEntitlement::deny('authentication_required');
            }

            if ($this->hasExceededDailyLimit($user)) {
                return DownloadEntitlement::deny('daily_limit_reached');
            }

            return DownloadEntitlement::allow('free');
        }

        if (! $user) {
            return DownloadEntitlement::deny('authentication_required');
        }

        // --- Purchase ---------------------------------------------------
        // Checked before subscription: an outright purchase is a permanent
        // entitlement that must keep working after a subscription lapses.
        // subscription_only assets are excluded — they are never sold à la carte.
        if (! $asset->subscription_only && $this->hasPurchased($user, $asset)) {
            return DownloadEntitlement::allow('purchase');
        }

        // --- Subscription -----------------------------------------------
        $plan = $this->activePlanFor($user);

        if ($plan) {
            if (! $plan->premium_access) {
                return DownloadEntitlement::deny('plan_excludes_premium');
            }

            if (! $this->planAllowsAssetType($plan, $asset)) {
                return DownloadEntitlement::deny('plan_excludes_asset_type');
            }

            if ($this->hasExceededPlanLimit($user, $plan)) {
                return DownloadEntitlement::deny('plan_limit_reached');
            }

            return DownloadEntitlement::allow('subscription');
        }

        return DownloadEntitlement::deny(
            $asset->subscription_only ? 'subscription_required' : 'purchase_required'
        );
    }

    private function isPublished(Asset $asset): bool
    {
        return $asset->status === 'published'
            && $asset->published_at !== null
            && $asset->published_at->lessThanOrEqualTo(now());
    }

    /**
     * A purchase counts only when it sits on a PAID order. A pending or
     * failed order must never grant access — this join is the difference
     * between "started a checkout" and "paid for it".
     */
    private function hasPurchased(User $user, Asset $asset): bool
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.user_id', $user->id)
            ->whereIn('orders.status', ['paid', 'partially_refunded'])
            ->whereNull('orders.deleted_at')
            ->where(function ($q) use ($asset) {
                $q->where('order_items.asset_id', $asset->id);

                // A pack purchase entitles every asset inside that pack.
                if ($asset->pack_id) {
                    $q->orWhere('order_items.pack_id', $asset->pack_id);
                }
            })
            ->exists();
    }

    /**
     * Resolves the plan behind the user's active subscription, including
     * one inherited from a team they belong to.
     *
     * `ends_at` is honoured rather than a cancelled flag, so a customer
     * who cancels mid-period keeps access through the grace period they
     * already paid for.
     */
    private function activePlanFor(User $user): ?Plan
    {
        $subscription = DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->first();

        if ($subscription) {
            $plan = $this->planFromSubscription($subscription);

            if ($plan) {
                return $plan;
            }
        }

        return $this->teamPlanFor($user);
    }

    private function planFromSubscription(object $subscription): ?Plan
    {
        $priceId = DB::table('subscription_items')
            ->where('subscription_id', $subscription->id)
            ->value('stripe_price');

        if ($priceId) {
            $plan = Plan::where('stripe_monthly_price_id', $priceId)
                ->orWhere('stripe_annual_price_id', $priceId)
                ->first();

            if ($plan) {
                return $plan;
            }
        }

        // Cashier stores the plan slug in `type` for non-Stripe-priced
        // plans (local/manual billing), so fall back to that.
        return Plan::where('slug', $subscription->type)->first();
    }

    private function teamPlanFor(User $user): ?Plan
    {
        $teamIds = DB::table('team_members')
            ->where('user_id', $user->id)
            ->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return null;
        }

        $ownerIds = DB::table('teams')
            ->whereIn('id', $teamIds)
            ->where('is_active', true)
            ->pluck('owner_id');

        if ($ownerIds->isEmpty()) {
            return null;
        }

        $subscription = DB::table('subscriptions')
            ->whereIn('user_id', $ownerIds)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->first();

        return $subscription ? $this->planFromSubscription($subscription) : null;
    }

    private function planAllowsAssetType(Plan $plan, Asset $asset): bool
    {
        $allowed = $plan->allowed_asset_types;

        // NULL means "no restriction". An empty array would mean "nothing
        // allowed", which is a different statement — so only NULL passes.
        if ($allowed === null || $allowed === []) {
            return true;
        }

        return in_array($asset->asset_type_id, $allowed, false);
    }

    private function hasExceededDailyLimit(User $user): bool
    {
        $limit = (int) $this->settings->get('free_download_limit_daily', 10);

        if ($limit <= 0) {
            return false;
        }

        return $this->countDownloadsSince($user, now()->startOfDay(), 'free') >= $limit;
    }

    private function hasExceededPlanLimit(User $user, Plan $plan): bool
    {
        if ($plan->download_limit_daily !== null
            && $this->countDownloadsSince($user, now()->startOfDay(), 'subscription') >= $plan->download_limit_daily) {
            return true;
        }

        if ($plan->download_limit_monthly !== null
            && $this->countDownloadsSince($user, now()->startOfMonth(), 'subscription') >= $plan->download_limit_monthly) {
            return true;
        }

        return false;
    }

    /**
     * Distinct asset count, not row count: re-downloading a file you
     * already have should not burn another day's quota.
     */
    private function countDownloadsSince(User $user, \DateTimeInterface $since, string $accessType): int
    {
        return DB::table('downloads')
            ->where('user_id', $user->id)
            ->where('access_type', $accessType)
            ->where('created_at', '>=', $since)
            ->distinct()
            ->count('asset_id');
    }
}
