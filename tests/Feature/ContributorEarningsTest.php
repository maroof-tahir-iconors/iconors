<?php

namespace Tests\Feature;

use App\Models\Contributor;
use App\Models\User;
use App\Services\Contributor\EarningsService;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contributor earnings, balances and payouts — brief §19, §23.
 *
 * This is the one part of the system where a bug moves real money to the
 * wrong person, so the arithmetic and the guards are tested directly.
 */
class ContributorEarningsTest extends TestCase
{
    use RefreshDatabase;

    private EarningsService $earnings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->earnings = app(EarningsService::class);
    }

    // ---------------------------------------------------------------
    // Rates
    // ---------------------------------------------------------------

    public function test_rates_come_from_settings(): void
    {
        $contributor = $this->contributor();

        $this->assertSame(50.0, $this->earnings->rateFor('individual_sale', $contributor));
        $this->assertSame(30.0, $this->earnings->rateFor('subscription_download', $contributor));
        $this->assertSame(25.0, $this->earnings->rateFor('api_download', $contributor));

        app(SettingsService::class)->set('commission_rate_one_time', 65);

        $this->assertSame(65.0, $this->earnings->rateFor('individual_sale', $contributor));
    }

    /** A negotiated per-contributor rate must beat the platform default. */
    public function test_a_per_contributor_override_wins(): void
    {
        $contributor = $this->contributor();
        $contributor->update(['commission_rate' => 70]);

        $this->assertSame(70.0, $this->earnings->rateFor('individual_sale', $contributor->fresh()));
    }

    // ---------------------------------------------------------------
    // Recording
    // ---------------------------------------------------------------

    public function test_an_earning_records_the_correct_net_and_a_ledger_entry(): void
    {
        $contributor = $this->contributor();

        $this->earnings->record($contributor, 'individual_sale', 20.00);

        // 50% of 20.00
        $this->assertDatabaseHas('contributor_earnings', [
            'contributor_id'  => $contributor->id,
            'gross_amount'    => '20.0000',
            'commission_rate' => '50.00',
            'net_amount'      => '10.0000',
            'status'          => 'pending',
        ]);

        // Money never moves without a ledger row.
        $this->assertDatabaseHas('ledger_entries', [
            'owner_type' => Contributor::class,
            'owner_id'   => $contributor->id,
            'direction'  => 'credit',
            'amount'     => '10.0000',
        ]);
    }

    /**
     * The most important guarantee in the whole royalty system: changing
     * the global rate must never alter what someone already earned.
     */
    public function test_changing_the_global_rate_does_not_alter_past_earnings(): void
    {
        $contributor = $this->contributor();

        $this->earnings->record($contributor, 'individual_sale', 100.00);
        $this->assertSame('50.0000', DB::table('contributor_earnings')->value('net_amount'));

        app(SettingsService::class)->set('commission_rate_one_time', 10);

        // The historic row is untouched…
        $this->assertSame('50.0000', DB::table('contributor_earnings')->orderBy('id')->value('net_amount'));

        // …and only new earnings use the new rate.
        $this->earnings->record($contributor, 'individual_sale', 100.00);
        $this->assertSame('10.0000', DB::table('contributor_earnings')->orderByDesc('id')->value('net_amount'));
    }

    public function test_earnings_start_pending_and_mature_after_the_holding_period(): void
    {
        $contributor = $this->contributor();
        app(SettingsService::class)->set('payout_holding_days', 30);

        $this->earnings->record($contributor, 'individual_sale', 40.00);

        $summary = $this->earnings->summaryFor($contributor->fresh());
        $this->assertSame(20.0, $summary['pending']);
        $this->assertSame(0.0, $summary['available']);

        // The holding period is what protects against paying out on a sale
        // that is refunded afterwards.
        $this->travel(31)->days();
        $this->earnings->releaseMatured();

        $summary = $this->earnings->summaryFor($contributor->fresh());
        $this->assertSame(0.0, $summary['pending']);
        $this->assertSame(20.0, $summary['available']);
    }

    /**
     * The cached balance is derived, never incremented. Recomputing must
     * be idempotent — running it twice cannot double anything.
     */
    public function test_recomputing_the_balance_is_idempotent(): void
    {
        $contributor = $this->contributor();

        $this->earnings->record($contributor, 'individual_sale', 30.00);
        $this->earnings->refreshBalance($contributor);
        $this->earnings->refreshBalance($contributor);
        $this->earnings->refreshBalance($contributor);

        $this->assertSame(15.0, $this->earnings->summaryFor($contributor->fresh())['pending']);
    }

    public function test_a_zero_value_earning_is_not_recorded(): void
    {
        $contributor = $this->contributor();

        $this->earnings->record($contributor, 'individual_sale', 0.0);

        $this->assertDatabaseCount('contributor_earnings', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    // ---------------------------------------------------------------
    // Payouts
    // ---------------------------------------------------------------

    public function test_a_payout_cannot_be_requested_below_the_minimum(): void
    {
        [$user, $contributor] = $this->approvedContributorUser();

        $this->earnings->record($contributor, 'individual_sale', 20.00);   // 10.00 net
        $this->matureEverything($contributor);

        $this->actingAs($user)
            ->post(route('contributor.payout.request'))
            ->assertSessionHasErrors('payout');

        $this->assertDatabaseCount('payout_requests', 0);
    }

    public function test_a_payout_can_be_requested_once_the_minimum_is_met(): void
    {
        [$user, $contributor] = $this->approvedContributorUser();

        $this->earnings->record($contributor, 'individual_sale', 200.00);  // 100.00 net
        $this->matureEverything($contributor);

        $this->actingAs($user)
            ->post(route('contributor.payout.request'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payout_requests', [
            'contributor_id' => $contributor->id,
            'amount'         => '100.0000',
            'status'         => 'requested',
        ]);
    }

    /**
     * Without this, a double-submit queues the same balance twice and the
     * platform pays it out twice.
     */
    public function test_a_second_payout_cannot_be_opened_while_one_is_pending(): void
    {
        [$user, $contributor] = $this->approvedContributorUser();

        $this->earnings->record($contributor, 'individual_sale', 400.00);
        $this->matureEverything($contributor);

        $this->actingAs($user)->post(route('contributor.payout.request'));
        $this->actingAs($user)->post(route('contributor.payout.request'))
            ->assertSessionHasErrors('payout');

        $this->assertDatabaseCount('payout_requests', 1);
    }

    /** Pending earnings are still inside the holding period, so ineligible. */
    public function test_pending_earnings_cannot_be_withdrawn(): void
    {
        [$user, $contributor] = $this->approvedContributorUser();

        $this->earnings->record($contributor, 'individual_sale', 400.00);  // 200.00, still pending

        $this->actingAs($user)
            ->post(route('contributor.payout.request'))
            ->assertSessionHasErrors('payout');
    }

    // ---------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------

    public function test_a_customer_cannot_reach_the_contributor_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Free Customer');

        $this->actingAs($user)->get(route('contributor.dashboard'))
            ->assertRedirect(route('contributor.apply'));
    }

    public function test_a_contributor_only_sees_their_own_figures(): void
    {
        [$userA, $contributorA] = $this->approvedContributorUser('a@iconors.test', 'Maker A');
        [, $contributorB]       = $this->approvedContributorUser('b@iconors.test', 'Maker B');

        $this->earnings->record($contributorA, 'individual_sale', 100.00);   // 50 net
        $this->earnings->record($contributorB, 'individual_sale', 900.00);   // 450 net

        $summary = $this->earnings->summaryFor($contributorA->fresh());

        $this->assertSame(50.0, $summary['pending']);
        $this->assertSame(50.0, $summary['lifetime']);
    }

    // ---------------------------------------------------------------

    private function contributor(): Contributor
    {
        return $this->approvedContributorUser()[1];
    }

    /** @return array{0: User, 1: Contributor} */
    private function approvedContributorUser(?string $email = null, string $name = 'Test Maker'): array
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->assignRole('Contributor');

        $contributor = Contributor::create([
            'user_id'      => $user->id,
            'display_name' => $name,
            'slug'         => \Illuminate\Support\Str::slug($name).'-'.$user->id,
            'status'       => 'approved',
        ]);

        return [$user->fresh(), $contributor];
    }

    private function matureEverything(Contributor $contributor): void
    {
        DB::table('contributor_earnings')
            ->where('contributor_id', $contributor->id)
            ->update(['status' => 'available']);

        $this->earnings->refreshBalance($contributor);
    }
}
