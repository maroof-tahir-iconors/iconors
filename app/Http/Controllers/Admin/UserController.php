<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * User administration — brief §25.
 *
 * Two rules run through everything here:
 *
 *  1. Nobody may escalate their own privileges. An administrator cannot
 *     edit their own roles, and cannot grant a role they do not hold
 *     themselves — otherwise "manage users" quietly becomes "become
 *     super administrator".
 *  2. Every state change is audited with actor, target and before/after.
 */
class UserController extends Controller
{
    private const IMPERSONATION_KEY = 'impersonator_id';

    public function index(Request $request)
    {
        $this->gate($request, 'users.view');

        $users = User::query()
            ->with('roles:id,name')
            ->when($request->query('q'), function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->query('role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when($request->query('status') === 'suspended', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'Suspended User')))
            ->when($request->query('status') === 'unverified', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'counts' => [
                'total'      => User::count(),
                'verified'   => User::whereNotNull('email_verified_at')->count(),
                'suspended'  => User::whereHas('roles', fn ($r) => $r->where('name', 'Suspended User'))->count(),
                'newThisWeek'=> User::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }

    public function show(Request $request, User $user)
    {
        $this->gate($request, 'users.view');

        return view('admin.users.show', [
            'user'          => $user->load('roles', 'contributor'),
            'roles'         => Role::orderBy('name')->pluck('name'),
            'assignable'    => $this->assignableRoles($request->user()),
            'downloads'     => DB::table('downloads')->where('user_id', $user->id)->count(),
            'orders'        => DB::table('orders')->where('user_id', $user->id)->whereNull('deleted_at')->count(),
            'lastDownloads' => DB::table('downloads')
                ->join('assets', 'assets.id', '=', 'downloads.asset_id')
                ->where('downloads.user_id', $user->id)
                ->orderByDesc('downloads.id')
                ->limit(10)
                ->get(['assets.title', 'downloads.access_type', 'downloads.created_at']),
            'activity' => DB::table('audit_logs')
                ->where('target_type', User::class)
                ->where('target_id', $user->id)
                ->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function updateRoles(Request $request, User $user)
    {
        $this->gate($request, 'roles.manage');

        $actor = $request->user();

        // Self-escalation guard. Editing your own roles is how a compromised
        // or careless admin session becomes a permanent super administrator.
        if ($actor->id === $user->id) {
            return back()->withErrors(['roles' => 'You cannot change your own roles. Ask another administrator.']);
        }

        $validated = $request->validate([
            'roles'   => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $requested  = $validated['roles'] ?? [];
        $assignable = $this->assignableRoles($actor);
        $refused    = array_diff($requested, $assignable);

        // You may not grant authority you do not hold.
        if ($refused !== []) {
            return back()->withErrors([
                'roles' => 'You cannot grant a role you do not hold yourself: '.implode(', ', $refused),
            ]);
        }

        $before = $user->getRoleNames()->all();
        $user->syncRoles($requested);

        $this->audit($request, 'user.roles_changed', $user, ['roles' => $before], ['roles' => $requested]);

        return back()->with('status', "Roles updated for {$user->name}.");
    }

    public function suspend(Request $request, User $user)
    {
        $this->gate($request, 'users.edit.all');

        if ($actorIsTarget = $request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot suspend your own account.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $before = $user->getRoleNames()->all();

        // Suspension strips every grant rather than setting a flag, so no
        // other part of the app has to remember to check a boolean.
        $user->syncRoles(['Suspended User']);

        // Log them out everywhere immediately — a suspended user with a live
        // session is still a logged-in user until it expires.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit($request, 'user.suspended', $user, ['roles' => $before], ['reason' => $validated['reason']]);

        return back()->with('status', "{$user->name} has been suspended and signed out everywhere.");
    }

    public function restore(Request $request, User $user)
    {
        $this->gate($request, 'users.edit.all');

        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        if (! in_array($validated['role'], $this->assignableRoles($request->user()), true)) {
            return back()->withErrors(['role' => 'You cannot grant that role.']);
        }

        $user->syncRoles([$validated['role']]);

        $this->audit($request, 'user.restored', $user, ['roles' => ['Suspended User']], ['roles' => [$validated['role']]]);

        return back()->with('status', "{$user->name} has been restored as {$validated['role']}.");
    }

    public function verify(Request $request, User $user)
    {
        $this->gate($request, 'users.edit.all');

        if ($user->email_verified_at) {
            return back()->with('status', 'That account is already verified.');
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->audit($request, 'user.verified', $user, [], []);

        return back()->with('status', "{$user->name} marked as verified.");
    }

    public function forceLogout(Request $request, User $user)
    {
        $this->gate($request, 'users.force_logout');

        $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit($request, 'user.force_logout', $user, [], ['sessions_cleared' => $deleted]);

        return back()->with('status', "Signed {$user->name} out of {$deleted} ".Str::plural('session', $deleted).'.');
    }

    /**
     * Impersonation — brief §25 "securely impersonate users".
     *
     * The original admin id is kept in the session so the action is
     * reversible and attributable. Staff accounts cannot be impersonated:
     * that would be a straightforward privilege-escalation path.
     */
    public function impersonate(Request $request, User $user)
    {
        $this->gate($request, 'users.impersonate');

        $actor = $request->user();

        if ($actor->id === $user->id) {
            return back()->withErrors(['user' => 'You are already signed in as that account.']);
        }

        if ($user->roles->intersect($this->staffRoles())->isNotEmpty()) {
            return back()->withErrors([
                'user' => 'Staff accounts cannot be impersonated — that would be a privilege-escalation route.',
            ]);
        }

        $this->audit($request, 'user.impersonated', $user, [], ['by' => $actor->email]);

        $request->session()->put(self::IMPERSONATION_KEY, $actor->id);
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('status', "You are now viewing the site as {$user->name}.");
    }

    public function stopImpersonating(Request $request)
    {
        $originalId = $request->session()->pull(self::IMPERSONATION_KEY);

        if (! $originalId || ! ($original = User::find($originalId))) {
            return redirect()->route('home');
        }

        Auth::login($original);

        return redirect()->route('admin.users.index')->with('status', 'Impersonation ended.');
    }

    public function forcePasswordReset(Request $request, User $user)
    {
        $this->gate($request, 'users.edit.all');

        // Randomised, never shown: the point is to lock the account out of
        // its current credentials, not to hand anyone a working password.
        $user->forceFill(['password' => Hash::make(Str::random(48))])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit($request, 'user.password_force_reset', $user, [], []);

        return back()->with('status', "{$user->name}'s password was invalidated and their sessions cleared. They must use “forgot password”.");
    }

    /**
     * Roles the actor is permitted to hand out: only those they already
     * hold. A Super Administrator holds every role and so may grant any.
     */
    private function assignableRoles(User $actor): array
    {
        if ($actor->hasRole('Super Administrator')) {
            return Role::pluck('name')->all();
        }

        return $actor->getRoleNames()->all();
    }

    private function staffRoles()
    {
        return Role::whereIn('name', [
            'Super Administrator', 'Administrator', 'Asset Manager', 'Asset Reviewer',
            'Contributor Manager', 'Finance Manager', 'Support Manager', 'Support Agent',
            'SEO Manager', 'Content Editor', 'Marketing Manager', 'Analyst',
        ])->get();
    }

    private function audit(Request $request, string $action, User $target, array $old, array $new): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $request->user()->id,
            'actor_label' => $request->user()->email,
            'action'      => $action,
            'target_type' => User::class,
            'target_id'   => $target->id,
            'old_values'  => $old ? json_encode($old) : null,
            'new_values'  => $new ? json_encode($new) : null,
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }

    private function gate(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}
