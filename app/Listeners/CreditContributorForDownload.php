<?php

namespace App\Listeners;

use App\Events\SubscriptionDownloadRecorded;
use App\Models\Contributor;
use App\Services\Contributor\EarningsService;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Credits a contributor when a subscriber downloads their asset — §23.
 *
 * Queued so a slow royalty calculation never delays the customer's file
 * transfer. The download itself is already recorded by the time this runs.
 *
 * The subscription pool model: each subscription download is worth a small
 * fixed amount, configurable, from which the contributor takes their
 * subscription commission. This stands in for a full revenue-share pool
 * until subscriptions are actually billing.
 */
class CreditContributorForDownload implements ShouldQueue
{
    public function __construct(
        private readonly EarningsService $earnings,
        private readonly SettingsService $settings,
    ) {}

    public function handle(SubscriptionDownloadRecorded $event): void
    {
        $download = $event->download;

        if (! $download->contributor_id) {
            return;
        }

        $contributor = Contributor::find($download->contributor_id);

        if (! $contributor || $contributor->status !== 'approved') {
            return;
        }

        $perDownload = (float) $this->settings->get('subscription_value_per_download', 0.10);

        if ($perDownload <= 0) {
            return;
        }

        $this->earnings->record(
            contributor: $contributor,
            source: 'subscription_download',
            grossAmount: $perDownload,
            assetId: $download->asset_id,
            downloadId: $download->id,
        );
    }
}
