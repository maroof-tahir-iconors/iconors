<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\ContributorApplication;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function create(Request $request, SettingsService $settings)
    {
        if (! $settings->get('contributor_applications_open', true)) {
            return view('contributor.applications-closed');
        }

        $existing = ContributorApplication::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        // An open application blocks a second one; a rejected or
        // more-info one does not, so applicants can revise and resubmit.
        if ($existing && in_array($existing->status, ['pending', 'under_review', 'approved'], true)) {
            return redirect()->route('contributor.apply.status');
        }

        return view('contributor.apply', ['existing' => $existing]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'     => ['required', 'string', 'max:160'],
            'display_name'  => ['required', 'string', 'max:120'],
            'country'       => ['required', 'string', 'size:2'],
            'biography'     => ['required', 'string', 'min:40', 'max:2000'],
            'portfolio_url' => ['required', 'url', 'max:255'],
            'website'       => ['nullable', 'url', 'max:255'],
            'specialties'   => ['required', 'array', 'min:1'],
            'specialties.*' => ['string', 'max:60'],
            'terms'         => ['accepted'],
        ], [
            'terms.accepted'    => 'You must accept the contributor terms.',
            'biography.min'     => 'Please tell us a little more about your work (at least 40 characters).',
        ]);

        // Guard against a double-submit creating two open applications.
        $open = ContributorApplication::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'under_review', 'approved'])
            ->exists();

        if ($open) {
            return redirect()->route('contributor.apply.status');
        }

        ContributorApplication::create([
            'user_id'           => $request->user()->id,
            'full_name'         => $validated['full_name'],
            'display_name'      => $validated['display_name'],
            'country'           => strtoupper($validated['country']),
            'biography'         => $validated['biography'],
            'portfolio_url'     => $validated['portfolio_url'],
            'website'           => $validated['website'] ?? null,
            'specialties'       => $validated['specialties'],
            'terms_accepted'    => true,
            'terms_accepted_at' => now(),
            'status'            => 'pending',
        ]);

        return redirect()
            ->route('contributor.apply.status')
            ->with('status', 'Your application has been submitted. We usually review within 3 working days.');
    }

    public function status(Request $request)
    {
        $application = ContributorApplication::where('user_id', $request->user()->id)
            ->latest()
            ->firstOrFail();

        return view('contributor.apply-status', ['application' => $application]);
    }
}
