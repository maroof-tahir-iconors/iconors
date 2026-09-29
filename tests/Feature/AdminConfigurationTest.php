<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use App\Services\Download\DownloadEntitlementService;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Everything the operator must be able to change without a developer —
 * brief §22 (no hard-coded pricing), §23 (configurable royalties), §30.
 *
 * The tests that matter most here are the ones proving a setting actually
 * CHANGES BEHAVIOUR. A setting that saves correctly but is never read is
 * worse than no setting: the admin believes they have control they do not.
 */
class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    // ---------------------------------------------------------------
    // Subscription plan pricing
    // ---------------------------------------------------------------

    public function test_an_admin_can_create_a_priced_plan(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.plans.store'), $this->planPayload())
            ->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('slug', 'individual-monthly')->first();

        $this->assertNotNull($plan);
        $this->assertSame('9.0000', (string) $plan->price_monthly);
        $this->assertSame('84.0000', (string) $plan->price_annual);
        $this->assertTrue((bool) $plan->premium_access);
    }

    public function test_plan_prices_can_be_changed_without_touching_code(): void
    {
        $plan = Plan::create(['name' => 'Individual', 'slug' => 'individual', 'price_monthly' => 9, 'price_annual' => 84]);

        $this->actingAs($this->admin())
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'name'          => 'Individual',
                'slug'          => 'individual',
                'price_monthly' => 12.50,
                'price_annual'  => 120,
            ]));

        $plan->refresh();
        $this->assertSame('12.5000', (string) $plan->price_monthly);

        // A price change is exactly the kind of thing someone later
        // disputes, so before/after must be on the audit trail.
        $log = DB::table('audit_logs')->where('action', 'plan.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('9.0000', (string) $log->old_values);
    }

    /**
     * NULL and 0 mean different things on a download limit: no ceiling
     * versus no downloads. A form that collapses them removes the ability
     * to express an unlimited plan.
     */
    public function test_a_blank_download_limit_means_unlimited_not_zero(): void
    {
        $this->actingAs($this->admin())->post(route('admin.plans.store'), $this->planPayload([
            'download_limit_daily' => null,
        ]));

        $this->assertNull(Plan::where('slug', 'individual-monthly')->first()->download_limit_daily);

        $this->actingAs($this->admin())->post(route('admin.plans.store'), $this->planPayload([
            'name'                 => 'Capped',
            'slug'                 => 'capped',
            'download_limit_daily' => 0,
        ]));

        $this->assertSame(0, (int) Plan::where('slug', 'capped')->first()->download_limit_daily);
    }

    /**
     * Regression. Blank labels are dropped, but the checkbox arrays keep
     * their original indices. Compacting the labels and then reading the
     * checkboxes by the new position shifts every flag below a blanked row
     * onto the wrong feature — so a feature that IS included gets stored as
     * excluded, and the pricing page lies to customers about what they get.
     */
    public function test_feature_flags_stay_aligned_when_a_middle_row_is_blanked(): void
    {
        $this->actingAs($this->admin())->post(route('admin.plans.store'), $this->planPayload([
            'feature_label' => [
                0 => 'Unlimited downloads',
                1 => '',                      // cleared by the admin
                2 => 'Commercial licence',
            ],
            'feature_included' => [
                0 => '1',
                1 => '1',
                2 => '1',
            ],
            'feature_highlighted' => [
                0 => '0',
                1 => '0',
                2 => '1',                     // belongs to "Commercial licence"
            ],
        ]));

        $features = Plan::where('slug', 'individual-monthly')->first()
            ->features()->orderBy('sort_order')->get();

        $this->assertCount(2, $features);
        $this->assertSame('Unlimited downloads', $features[0]->label);
        $this->assertSame('Commercial licence', $features[1]->label);

        // The highlight must have followed its own row, not slid up one.
        $this->assertFalse((bool) $features[0]->is_highlighted);
        $this->assertTrue((bool) $features[1]->is_highlighted);
        $this->assertTrue((bool) $features[1]->is_included);
    }

    public function test_a_plan_with_active_subscribers_cannot_be_deleted(): void
    {
        $plan = Plan::create(['name' => 'Live Plan', 'slug' => 'live-plan', 'price_monthly' => 9, 'price_annual' => 90]);

        DB::table('subscriptions')->insert([
            'user_id'       => User::factory()->create()->id,
            'type'          => 'live-plan',
            'stripe_id'     => 'sub_test',
            'stripe_status' => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.plans.destroy', $plan))
            ->assertSessionHasErrors('plan');

        // Deleting would orphan the subscriber's entitlement resolution.
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
    }

    public function test_a_marketing_manager_cannot_change_plan_pricing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Marketing Manager');

        $this->actingAs($user)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.plans.store'), $this->planPayload())->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Licence terms
    // ---------------------------------------------------------------

    public function test_an_admin_can_write_licence_terms(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.licenses.store'), $this->licensePayload())
            ->assertRedirect(route('admin.licenses.index'));

        $license = License::where('slug', 'standard-licence')->first();

        $this->assertNotNull($license);
        $this->assertTrue((bool) $license->allows_commercial);
        $this->assertStringContainsString('worldwide', $license->full_text);
    }

    public function test_licence_full_text_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.licenses.store'), $this->licensePayload(['full_text' => '']))
            ->assertSessionHasErrors('full_text');
    }

    public function test_a_licence_in_use_cannot_be_deleted(): void
    {
        $license = License::create([
            'name' => 'In Use', 'slug' => 'in-use',
            'summary' => 'x', 'full_text' => 'x',
        ]);

        $this->makeAsset(['license_id' => $license->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.licenses.destroy', $license))
            ->assertSessionHasErrors('license');

        $this->assertDatabaseHas('licenses', ['id' => $license->id]);
    }

    // ---------------------------------------------------------------
    // Royalty and payout rules
    // ---------------------------------------------------------------

    public function test_commission_rates_and_payout_rules_are_configurable(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.royalties'), $this->royaltyPayload([
                'commission_rate_one_time' => 60,
                'payout_holding_days'      => 45,
                'refund_window_days'       => 30,
            ]))
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);

        $this->assertSame(60.0, (float) $settings->get('commission_rate_one_time'));
        $this->assertSame(45, (int) $settings->get('payout_holding_days'));
        $this->assertSame(30, (int) $settings->get('refund_window_days'));
    }

    public function test_a_commission_rate_above_one_hundred_percent_is_rejected(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.royalties'), $this->royaltyPayload([
                'commission_rate_one_time' => 140,
            ]))
            ->assertSessionHasErrors('commission_rate_one_time');
    }

    /**
     * Paying out before the refund window closes means the platform
     * absorbs any clawback. Some operators accept that, so this warns
     * rather than blocks — but it must not pass silently.
     */
    public function test_a_holding_period_shorter_than_the_refund_window_warns(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.royalties'), $this->royaltyPayload([
                'payout_holding_days' => 7,
                'refund_window_days'  => 30,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');
    }

    public function test_a_support_agent_cannot_change_commission_rates(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Support Agent');

        $this->actingAs($user)
            ->post(route('admin.settings.royalties'), $this->royaltyPayload())
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Marketplace rules — and proof they take effect
    // ---------------------------------------------------------------

    public function test_marketplace_rules_are_configurable(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.marketplace'), $this->marketplacePayload([
                'free_download_limit_daily' => 3,
                'download_url_ttl_minutes'  => 10,
            ]))
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);

        $this->assertSame(3, (int) $settings->get('free_download_limit_daily'));
        $this->assertSame(10, (int) $settings->get('download_url_ttl_minutes'));
    }

    /**
     * The important one. Proves the admin setting is actually READ by the
     * entitlement service — not merely stored and forgotten.
     */
    public function test_changing_the_free_download_limit_changes_real_behaviour(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.marketplace'), $this->marketplacePayload([
            'free_download_limit_daily' => 2,
        ]));

        $user = User::factory()->create();

        // Burn the two downloads the new limit allows.
        foreach (range(1, 2) as $i) {
            DB::table('downloads')->insert([
                'user_id'     => $user->id,
                'asset_id'    => $this->makeAsset()->id,
                'access_type' => 'free',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $result = app(DownloadEntitlementService::class)->check($user, $this->makeAsset());

        $this->assertFalse($result->allowed);
        $this->assertSame('daily_limit_reached', $result->reason);
    }

    public function test_setting_the_free_limit_to_zero_means_unlimited(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.marketplace'), $this->marketplacePayload([
            'free_download_limit_daily' => 0,
        ]));

        $user = User::factory()->create();

        foreach (range(1, 25) as $i) {
            DB::table('downloads')->insert([
                'user_id'     => $user->id,
                'asset_id'    => $this->makeAsset()->id,
                'access_type' => 'free',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->assertTrue(
            app(DownloadEntitlementService::class)->check($user, $this->makeAsset())->allowed
        );
    }

    /**
     * Turning moderation off is a significant policy change — uploads
     * then publish with no human check at all. It must be flagged.
     */
    public function test_disabling_asset_review_warns_the_administrator(): void
    {
        $payload = $this->marketplacePayload();
        unset($payload['require_asset_review']);   // unticked switch

        $this->actingAs($this->admin())
            ->post(route('admin.settings.marketplace'), $payload)
            ->assertSessionHas('warning');

        $this->assertFalse((bool) app(SettingsService::class)->get('require_asset_review'));
    }

    /**
     * Regression: an unticked checkbox is absent from the request, so
     * Laravel drops it from the validated payload. Without the `_fields`
     * marker the controller cannot tell "switched off" from "not on this
     * form", and toggling a switch off silently does nothing.
     */
    public function test_switching_a_toggle_off_actually_persists(): void
    {
        $settings = app(SettingsService::class);
        $this->assertTrue((bool) $settings->get('watermark_previews'));

        $payload = $this->marketplacePayload();
        unset($payload['watermark_previews']);

        $this->actingAs($this->admin())->post(route('admin.settings.marketplace'), $payload);

        $this->assertFalse((bool) app(SettingsService::class)->get('watermark_previews'));
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        return $user;
    }

    private function financeManager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Finance Manager');

        return $user;
    }

    private function makeAsset(array $overrides = []): Asset
    {
        $type = AssetType::firstOrCreate(
            ['slug' => 'svg-icon'],
            ['name' => 'SVG Icon', 'allowed_extensions' => ['svg'], 'allowed_mimes' => ['image/svg+xml']],
        );

        $contributor = Contributor::firstOrCreate(
            ['slug' => 'test-contributor'],
            [
                'user_id'      => User::factory()->create()->id,
                'display_name' => 'Test Contributor',
                'status'       => 'approved',
            ],
        );

        static $n = 0;
        $n++;

        return Asset::create(array_merge([
            'contributor_id' => $contributor->id,
            'asset_type_id'  => $type->id,
            'title'          => "Config Asset {$n}",
            'slug'           => "config-asset-{$n}",
            'status'         => 'published',
            'published_at'   => now()->subDay(),
            'is_premium'     => false,
        ], $overrides));
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'Individual Monthly',
            'slug'                 => 'individual-monthly',
            'description'          => 'For solo designers.',
            'price_monthly'        => 9,
            'price_annual'         => 84,
            'currency'             => 'USD',
            'trial_days'           => 7,
            'download_limit_daily' => 50,
            'team_seats'           => 1,
            'premium_access'       => '1',
            'is_active'            => '1',
            'is_public'            => '1',
            'feature_label'        => ['Unlimited downloads', 'Commercial licence'],
            'feature_included'     => ['1', '1'],
        ], $overrides);
    }

    private function licensePayload(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'Standard Licence',
            'slug'                 => 'standard-licence',
            'summary'              => 'Use in unlimited personal and commercial projects.',
            'full_text'            => 'You are granted a perpetual, worldwide, non-exclusive licence to use '
                                     .'the asset in personal and commercial projects. Redistribution of the '
                                     .'source file is not permitted.',
            'allows_commercial'    => '1',
            'allows_modification'  => '1',
            'requires_attribution' => '1',
            'is_active'            => '1',
        ], $overrides);
    }

    private function royaltyPayload(array $overrides = []): array
    {
        return array_merge([
            'commission_rate_one_time'     => 50,
            'commission_rate_subscription' => 30,
            'commission_rate_api'          => 25,
            'payout_minimum'               => 50,
            'payout_holding_days'          => 30,
            'payout_schedule'              => 'monthly',
            'payout_deduct_refunds'        => '1',
            'payout_fee_fixed'             => 0,
            'payout_fee_percent'           => 0,
            'refund_window_days'           => 14,
            '_fields'                      => 'payout_deduct_refunds',
        ], $overrides);
    }

    private function marketplacePayload(array $overrides = []): array
    {
        return array_merge([
            'free_download_limit_daily' => 10,
            'upload_max_size_mb'        => 100,
            'require_asset_review'      => '1',
            'download_url_ttl_minutes'  => 5,
            'watermark_previews'        => '1',
            '_fields'                   => 'guest_download_enabled,require_asset_review,watermark_previews',
        ], $overrides);
    }
}
