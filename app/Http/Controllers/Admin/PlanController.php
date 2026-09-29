<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Subscription plan management — brief §22.
 *
 * Every price, limit and entitlement is a database row. Nothing about
 * pricing is hard-coded anywhere in the application, so a plan change is
 * an admin action rather than a deployment.
 */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $this->gate($request);

        return view('admin.plans.index', [
            'plans' => Plan::withCount('features')
                ->orderBy('sort_order')
                ->orderBy('price_monthly')
                ->get(),
            // Live subscriber counts, so an admin can see what a price
            // change would actually affect before making it.
            'subscriberCounts' => DB::table('subscriptions')
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),
        ]);
    }

    public function create(Request $request)
    {
        $this->gate($request);

        return view('admin.plans.edit', [
            'plan'     => new Plan(['is_active' => true, 'is_public' => true, 'team_seats' => 1]),
            'licenses' => License::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Request $request, Plan $plan)
    {
        $this->gate($request);

        return view('admin.plans.edit', [
            'plan'     => $plan->load('features'),
            'licenses' => License::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->gate($request);

        $plan = Plan::create($this->validated($request));
        $this->syncFeatures($plan, $request);
        $this->audit($request, 'plan.created', $plan);

        return redirect()->route('admin.plans.index')
            ->with('status', "Plan “{$plan->name}” created.");
    }

    public function update(Request $request, Plan $plan)
    {
        $this->gate($request);

        $before = $plan->only(['price_monthly', 'price_annual', 'download_limit_daily', 'premium_access']);

        $plan->update($this->validated($request, $plan));
        $this->syncFeatures($plan, $request);

        // Price changes are recorded with before/after, because "why did
        // this customer get billed a different amount" is a question that
        // will eventually be asked.
        $this->audit($request, 'plan.updated', $plan, $before);

        return redirect()->route('admin.plans.index')
            ->with('status', "Plan “{$plan->name}” updated.");
    }

    public function destroy(Request $request, Plan $plan)
    {
        $this->gate($request);

        $active = DB::table('subscriptions')
            ->where('type', $plan->slug)
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->count();

        // Deleting a plan with live subscribers would orphan their
        // entitlement resolution. Deactivating hides it from the pricing
        // page while existing subscribers keep working.
        if ($active > 0) {
            return back()->withErrors([
                'plan' => "“{$plan->name}” has {$active} active ".Str::plural('subscriber', $active)
                    .'. Deactivate it instead — existing subscribers keep their access, '
                    .'but nobody new can subscribe.',
            ]);
        }

        $plan->delete();
        $this->audit($request, 'plan.deleted', $plan);

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan deleted.');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $unique = $plan ? ",{$plan->id}" : '';

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'slug'        => ['required', 'string', 'max:140', "unique:plans,slug{$unique}"],
            'description' => ['nullable', 'string', 'max:1000'],

            'price_monthly' => ['required', 'numeric', 'min:0', 'max:99999'],
            'price_annual'  => ['required', 'numeric', 'min:0', 'max:99999'],
            'currency'      => ['required', 'string', 'size:3'],

            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_annual_price_id'  => ['nullable', 'string', 'max:255'],

            'trial_days'             => ['nullable', 'integer', 'min:0', 'max:365'],
            // Nullable means unlimited; 0 means none. Two different things,
            // so the form must be able to express both.
            'download_limit_daily'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'download_limit_monthly' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'credits_monthly'        => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'team_seats'             => ['nullable', 'integer', 'min:1', 'max:10000'],

            'premium_access'     => ['nullable', 'boolean'],
            'source_file_access' => ['nullable', 'boolean'],
            'editor_access'      => ['nullable', 'boolean'],
            'api_access'         => ['nullable', 'boolean'],
            'license_id'         => ['nullable', 'exists:licenses,id'],

            'is_team_plan' => ['nullable', 'boolean'],
            'is_featured'  => ['nullable', 'boolean'],
            'is_active'    => ['nullable', 'boolean'],
            'is_public'    => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        foreach ([
            'premium_access', 'source_file_access', 'editor_access', 'api_access',
            'is_team_plan', 'is_featured', 'is_active', 'is_public',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['slug'] = Str::slug($data['slug']);

        return $data;
    }

    /**
     * Replaces the feature list wholesale. Features are display-only
     * marketing copy for the pricing page — actual entitlement comes from
     * the boolean columns above, never from this text.
     */
    /**
     * Replaces the feature list wholesale.
     *
     * Lookups use each row's ORIGINAL submitted key, never a re-indexed
     * position. Compacting the labels with array_values() and then reading
     * `$highlighted[$i]` silently shifts every checkbox below a blanked-out
     * row onto the wrong feature — so "Commercial licence" ends up marked
     * as not-included because someone cleared the label two rows above it.
     * sort_order is assigned separately from the lookup key.
     */
    private function syncFeatures(Plan $plan, Request $request): void
    {
        $labels      = (array) $request->input('feature_label', []);
        $highlighted = (array) $request->input('feature_highlighted', []);
        $included    = (array) $request->input('feature_included', []);

        DB::transaction(function () use ($plan, $labels, $highlighted, $included) {
            PlanFeature::where('plan_id', $plan->id)->delete();

            $sortOrder = 0;

            foreach ($labels as $key => $label) {
                if (blank($label)) {
                    continue;
                }

                PlanFeature::create([
                    'plan_id'        => $plan->id,
                    'label'          => Str::limit((string) $label, 200, ''),
                    'is_highlighted' => (bool) ($highlighted[$key] ?? false),
                    'is_included'    => (bool) ($included[$key] ?? true),
                    'sort_order'     => $sortOrder++,
                ]);
            }
        });
    }

    private function audit(Request $request, string $action, Plan $plan, array $before = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $request->user()->id,
            'actor_label' => $request->user()->email,
            'action'      => $action,
            'target_type' => Plan::class,
            'target_id'   => $plan->id,
            'old_values'  => $before ? json_encode($before) : null,
            'new_values'  => json_encode($plan->only([
                'name', 'price_monthly', 'price_annual', 'download_limit_daily', 'premium_access',
            ])),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }

    private function gate(Request $request): void
    {
        abort_unless($request->user()?->can('subscriptions.edit.all'), 403);
    }
}
