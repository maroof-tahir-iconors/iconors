<?php

namespace App\Events;

use App\Models\Download;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a subscriber downloads a premium asset.
 *
 * Listeners convert this into a contributor royalty. It is an event
 * rather than a direct call so royalty accrual runs on the queue and a
 * slow payout calculation never delays the user's file transfer.
 */
class SubscriptionDownloadRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Download $download,
    ) {}
}
