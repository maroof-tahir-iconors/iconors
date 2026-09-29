<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use App\Models\ContributorApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contributor application lifecycle — brief §19.
 *
 * Approval is the moment a user gains upload rights, so it does three
 * things atomically: creates the contributor profile, grants the role,
 * and writes an audit record. A partial failure that granted the role
 * without the profile would leave a user who can reach upload forms but
 * has nothing to attach assets to.
 */
class ApplicationService
{
    public function approve(ContributorApplication $application, User $reviewer, ?string $notes = null): Contributor
    {
        return DB::transaction(function () use ($application, $reviewer, $notes) {
            $user = $application->user;

            $contributor = Contributor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $application->display_name,
                    'slug'         => $this->uniqueSlug($application->display_name),
                    'biography'    => $application->biography,
                    'country'      => $application->country,
                    'website'      => $application->website,
                    'portfolio_url'=> $application->portfolio_url,
                    'social_links' => $application->social_links,
                    'specialties'  => $application->specialties,
                    'status'       => 'approved',
                    'approved_at'  => now(),
                    'approved_by'  => $reviewer->id,
                ],
            );

            $application->update([
                'status'         => 'approved',
                'reviewed_by'    => $reviewer->id,
                'reviewed_at'    => now(),
                'reviewer_notes' => $notes,
            ]);

            $user->assignRole('Contributor');

            $this->audit($reviewer, 'contributor.approved', $contributor, [
                'application_id' => $application->id,
            ]);

            return $contributor;
        });
    }

    public function reject(ContributorApplication $application, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($application, $reviewer, $reason) {
            $application->update([
                'status'         => 'rejected',
                'reviewed_by'    => $reviewer->id,
                'reviewed_at'    => now(),
                'reviewer_notes' => $reason,
            ]);

            // Rejection does NOT delete the application. The applicant can
            // reapply, and the history is needed if they dispute the outcome.
            $this->audit($reviewer, 'contributor.rejected', $application, [
                'reason' => $reason,
            ]);
        });
    }

    public function requestMoreInfo(ContributorApplication $application, User $reviewer, string $notes): void
    {
        $application->update([
            'status'         => 'more_info_required',
            'reviewed_by'    => $reviewer->id,
            'reviewed_at'    => now(),
            'reviewer_notes' => $notes,
        ]);

        $this->audit($reviewer, 'contributor.more_info_requested', $application, ['notes' => $notes]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'contributor';
        $slug = $base;
        $i    = 1;

        while (Contributor::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function audit(User $actor, string $action, $target, array $values = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $actor->id,
            'actor_label' => $actor->email,
            'action'      => $action,
            'target_type' => $target::class,
            'target_id'   => $target->id,
            'new_values'  => json_encode($values),
            'ip_address'  => request()->ip(),
            'user_agent'  => substr((string) request()->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }
}
