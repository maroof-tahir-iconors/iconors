<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ContributorApplication;
use App\Services\Asset\AssetReviewService;
use App\Services\Contributor\ApplicationService;
use Illuminate\Http\Request;

/**
 * Admin moderation queues — brief §25.
 *
 * Every action here re-checks authorisation server-side. Hiding a button
 * in Blade is presentation, not security (brief §26: "never depend only
 * on hiding buttons").
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly AssetReviewService $assets,
    ) {}

    /* ---------------- Contributor applications ---------------- */

    public function applications(Request $request)
    {
        $this->authorizePermission($request, 'contributors.view');

        return view('admin.applications.index', [
            'applications' => ContributorApplication::with('user')
                ->when($request->query('status', 'pending'), fn ($q, $s) => $q->where('status', $s))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => ContributorApplication::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approveApplication(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.approve');

        if ($application->status === 'approved') {
            return back()->with('status', 'That application was already approved.');
        }

        $contributor = $this->applications->approve(
            $application,
            $request->user(),
            $request->input('notes'),
        );

        return back()->with('status', "{$contributor->display_name} is now an approved contributor.");
    }

    public function rejectApplication(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.reject');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            // A rejection with no explanation generates a support ticket
            // every time, so the reason is mandatory.
            'reason.required' => 'Please give the applicant a reason.',
        ]);

        $this->applications->reject($application, $request->user(), $validated['reason']);

        return back()->with('status', 'Application rejected.');
    }

    public function requestApplicationInfo(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.approve');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->applications->requestMoreInfo($application, $request->user(), $validated['notes']);

        return back()->with('status', 'More information requested.');
    }

    /* ---------------- Asset submissions ---------------- */

    public function assets(Request $request)
    {
        // assets.approve, not assets.view: contributors hold assets.view for
        // their own work, and this queue exposes everyone's unpublished
        // submissions.
        $this->authorizePermission($request, 'assets.approve');

        return view('admin.assets.review', [
            'assets' => Asset::with(['contributor', 'assetType', 'previews'])
                ->when(
                    $request->query('status', 'submitted'),
                    fn ($q, $s) => $q->where('status', $s)
                )
                ->latest()
                ->paginate(24)
                ->withQueryString(),
            'counts' => Asset::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approveAsset(Request $request, Asset $asset)
    {
        // Policy, not just permission: it also blocks a reviewer from
        // approving their own submission.
        $this->authorize('review', $asset);

        $this->assets->approve($asset, $request->user(), publishNow: true);

        return back()->with('status', "“{$asset->title}” is now published.");
    }

    public function requestAssetChanges(Request $request, Asset $asset)
    {
        $this->authorize('review', $asset);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->assets->requestChanges($asset, $request->user(), $validated['feedback']);

        return back()->with('status', 'Changes requested.');
    }

    public function rejectAsset(Request $request, Asset $asset)
    {
        $this->authorize('review', $asset);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->assets->reject($asset, $request->user(), $validated['reason']);

        return back()->with('status', 'Asset rejected.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}
