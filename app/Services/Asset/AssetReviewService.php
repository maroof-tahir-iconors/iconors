<?php

namespace App\Services\Asset;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Asset moderation — brief §9 workflow, §33 audit.
 *
 * Publishing is the only path by which an asset becomes visible in the
 * marketplace, so it is the single place that sets published_at. Any
 * other code setting that column would bypass moderation entirely.
 */
class AssetReviewService
{
    public function approve(Asset $asset, User $reviewer, bool $publishNow = true): Asset
    {
        return DB::transaction(function () use ($asset, $reviewer, $publishNow) {
            $previous = $asset->status;

            $asset->update([
                'status'        => $publishNow ? 'published' : 'approved',
                'published_at'  => $publishNow ? now() : null,
                'reviewed_by'   => $reviewer->id,
                'reviewed_at'   => now(),
                'status_reason' => null,
            ]);

            if ($publishNow) {
                $this->bumpCounters($asset);
            }

            $this->audit($reviewer, 'asset.approved', $asset, $previous, $asset->status);

            // Homepage rails are cached; a newly published asset must not
            // wait out the TTL before appearing.
            Cache::forget('home.sections');

            return $asset->fresh();
        });
    }

    public function requestChanges(Asset $asset, User $reviewer, string $feedback): Asset
    {
        $previous = $asset->status;

        $asset->update([
            'status'        => 'changes_requested',
            'status_reason' => $feedback,
            'reviewed_by'   => $reviewer->id,
            'reviewed_at'   => now(),
        ]);

        $this->audit($reviewer, 'asset.changes_requested', $asset, $previous, 'changes_requested');

        return $asset->fresh();
    }

    public function reject(Asset $asset, User $reviewer, string $reason): Asset
    {
        $previous = $asset->status;

        $asset->update([
            'status'        => 'rejected',
            'status_reason' => $reason,
            'reviewed_by'   => $reviewer->id,
            'reviewed_at'   => now(),
            // Explicitly cleared: a rejected asset must not remain
            // reachable through any published() scope.
            'published_at'  => null,
        ]);

        $this->audit($reviewer, 'asset.rejected', $asset, $previous, 'rejected');

        return $asset->fresh();
    }

    public function suspend(Asset $asset, User $reviewer, string $reason): Asset
    {
        $previous = $asset->status;

        $asset->update([
            'status'        => 'suspended',
            'status_reason' => $reason,
            'published_at'  => null,
        ]);

        $this->audit($reviewer, 'asset.suspended', $asset, $previous, 'suspended');
        Cache::forget('home.sections');

        return $asset->fresh();
    }

    private function bumpCounters(Asset $asset): void
    {
        DB::table('contributors')->where('id', $asset->contributor_id)->increment('assets_count');

        if ($asset->category_id) {
            DB::table('categories')->where('id', $asset->category_id)->increment('assets_count');
        }
    }

    private function audit(User $actor, string $action, Asset $asset, string $from, string $to): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $actor->id,
            'actor_label' => $actor->email,
            'action'      => $action,
            'target_type' => Asset::class,
            'target_id'   => $asset->id,
            'old_values'  => json_encode(['status' => $from]),
            'new_values'  => json_encode(['status' => $to]),
            'ip_address'  => request()->ip(),
            'user_agent'  => substr((string) request()->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }
}
