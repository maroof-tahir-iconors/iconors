<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

/**
 * Authorisation for assets — brief §26.
 *
 * "edit own" and "edit all" are distinct PERMISSIONS, not one permission
 * plus an ownership check here. That means a contributor who somehow
 * reaches an admin controller still cannot mutate someone else's asset:
 * the grant they hold does not extend that far.
 */
class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($asset->status === 'published') {
            return true;
        }

        // Unpublished assets are visible to their owner and to staff who
        // can see all assets — nobody else, so a direct-URL probe cannot
        // reveal an unreleased asset.
        return $this->owns($user, $asset)
            || $user->can('assets.edit.all')
            || $user->can('assets.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create') && $this->isApprovedContributor($user);
    }

    public function update(User $user, Asset $asset): bool
    {
        if ($user->can('assets.edit.all')) {
            return true;
        }

        if (! $user->can('assets.edit.own') || ! $this->owns($user, $asset)) {
            return false;
        }

        // A contributor may revise their own work only while it is still
        // theirs to revise. Once published, edits go through review again
        // rather than silently changing what customers already licensed.
        return in_array($asset->status, ['draft', 'changes_requested', 'rejected'], true);
    }

    public function delete(User $user, Asset $asset): bool
    {
        if ($user->can('assets.delete.all')) {
            return true;
        }

        // Deleting a published asset would orphan existing download
        // licences, so owners can only remove work that never shipped.
        return $user->can('assets.delete.own')
            && $this->owns($user, $asset)
            && in_array($asset->status, ['draft', 'rejected'], true);
    }

    public function submit(User $user, Asset $asset): bool
    {
        return $this->owns($user, $asset)
            && in_array($asset->status, ['draft', 'changes_requested'], true);
    }

    public function review(User $user, Asset $asset): bool
    {
        // A reviewer must not approve their own submission. Without this,
        // any staff member who is also a contributor becomes their own
        // rubber stamp.
        return $user->can('assets.approve') && ! $this->owns($user, $asset);
    }

    public function publish(User $user): bool
    {
        return $user->can('assets.publish');
    }

    private function owns(User $user, Asset $asset): bool
    {
        return $user->contributor !== null
            && (int) $user->contributor->id === (int) $asset->contributor_id;
    }

    private function isApprovedContributor(User $user): bool
    {
        return $user->contributor !== null && $user->contributor->status === 'approved';
    }
}
