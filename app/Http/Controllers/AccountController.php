<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Contributor;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The customer-facing "My Account" area.
 *
 * One controller per tab method rather than a single index with a `?tab=`
 * switch: each tab has its own route name, so the browser back button, deep
 * links and the active-tab highlight all work without extra state.
 */
class AccountController extends Controller
{
    /**
     * Overview — the account landing tab, served at /dashboard.
     *
     * The path and route name stay `dashboard` because the login, registration
     * and stop-impersonating redirects all point at it, as does the header.
     * Only the presentation moved into the account shell.
     */
    public function overview(): View
    {
        $user = auth()->user();

        return view('account.overview', [
            'user' => $user,
            'downloadsCount' => $user->downloads()->count(),
            'collectionsCount' => $user->collections()->count(),
            'likesCount' => $user->likes()->count(),
            'recentDownloads' => $user->downloads()
                ->with(['asset.previews', 'asset.assetType'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * My Collections — the landing tab, matching the reference design.
     */
    public function collections(): View
    {
        $collections = auth()->user()
            ->collections()
            ->withCount('assets')
            ->with(['assets' => fn ($q) => $q->with('previews')->limit(4)])
            ->latest()
            ->paginate(12);

        return view('account.collections', compact('collections'));
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $collection = $user->collections()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueCollectionSlug($data['name']),
            'description' => $data['description'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'share_token' => Str::random(32),
            'assets_count' => 0,
        ]);

        return redirect()
            ->route('account.collections')
            ->with('status', "Collection “{$collection->name}” created.");
    }

    public function destroyCollection(Request $request, Collection $collection): RedirectResponse
    {
        // Ownership check, not just auth — otherwise any signed-in user could
        // delete any collection by guessing an id.
        abort_unless($collection->user_id === $request->user()->id, 403);

        $name = $collection->name;
        $collection->delete();

        return redirect()
            ->route('account.collections')
            ->with('status', "Collection “{$name}” deleted.");
    }

    /**
     * Slugs are unique across the whole table, so a suffix is needed when two
     * users both make a "Favourites" collection.
     */
    private function uniqueCollectionSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $suffix = 2;

        while (Collection::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function downloads(): View
    {
        $downloads = auth()->user()
            ->downloads()
            ->with(['asset.previews', 'asset.assetType'])
            ->latest()
            ->paginate(20);

        return view('account.downloads', compact('downloads'));
    }

    public function following(): View
    {
        $following = auth()->user()
            ->following()
            ->withCount('assets')
            ->orderByPivot('created_at', 'desc')
            ->paginate(12);

        return view('account.following', compact('following'));
    }

    public function unfollow(Request $request, Contributor $contributor): RedirectResponse
    {
        $request->user()->following()->detach($contributor->id);

        $contributor->decrement('followers_count');

        return redirect()
            ->route('account.following')
            ->with('status', "Unfollowed {$contributor->display_name}.");
    }

    /**
     * API tab.
     *
     * There is no API yet — no Sanctum, no tokens table — so this renders an
     * honest "not available on your plan / coming soon" state rather than a
     * key generator that would mint tokens nothing accepts. `api_access` is
     * already a real column on `plans`, so the gating below is live.
     */
    public function api(): View
    {
        $plan = auth()->user()->currentPlan();

        return view('account.api', [
            'plan' => $plan,
            'hasApiAccess' => (bool) ($plan?->api_access ?? false),
            'apiPlans' => Plan::where('api_access', true)
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function details(): View
    {
        return view('account.details', [
            'user' => auth()->user(),
        ]);
    }

    public function updateDetails(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        // Changing the address invalidates the previous verification.
        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return redirect()
            ->route('account.details')
            ->with('status', 'Account details updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->password = $data['password']; // 'hashed' cast handles hashing
        $user->save();

        return redirect()
            ->route('account.details')
            ->with('status', 'Password updated.');
    }

    public function subscription(): View
    {
        $user = auth()->user();

        return view('account.subscription', [
            'plan' => $user->currentPlan(),
            'plans' => Plan::where('is_active', true)
                ->where('is_public', true)
                ->with(['features' => fn ($q) => $q->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get(),
            'downloadsToday' => $user->downloads()
                ->whereDate('created_at', today())
                ->count(),
        ]);
    }

    /**
     * Invite Members.
     *
     * Team seats are a real plan capability (`plans.team_seats`), but the team
     * dashboard itself is unbuilt, so this gates on the plan and explains the
     * upgrade rather than opening a half-wired invite form.
     */
    public function members(): View
    {
        $plan = auth()->user()->currentPlan();

        return view('account.members', [
            'plan' => $plan,
            'seats' => (int) ($plan?->team_seats ?? 1),
            'teamPlans' => Plan::where('is_team_plan', true)
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
