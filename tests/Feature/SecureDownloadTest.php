<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\Plan;
use App\Models\User;
use App\Services\Download\DownloadEntitlementService;
use App\Services\Download\SecureDownloadService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the acceptance criteria in brief §43 that concern downloads:
 *   - a user can download permitted free assets
 *   - premium assets require a valid purchase or subscription
 *   - secure download links expire
 */
class SecureDownloadTest extends TestCase
{
    use RefreshDatabase;

    private DownloadEntitlementService $entitlements;
    private SecureDownloadService $downloads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('private');

        $this->entitlements = app(DownloadEntitlementService::class);
        $this->downloads    = app(SecureDownloadService::class);
    }

    // ---------------------------------------------------------------
    // Free assets
    // ---------------------------------------------------------------

    public function test_authenticated_user_can_download_a_free_asset(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $user  = User::factory()->create();

        $result = $this->entitlements->check($user, $asset);

        $this->assertTrue($result->allowed);
        $this->assertSame('free', $result->accessType);
    }

    public function test_guest_cannot_download_even_a_free_asset(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);

        $result = $this->entitlements->check(null, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('authentication_required', $result->reason);
    }

    public function test_free_download_daily_limit_is_enforced(): void
    {
        $user = User::factory()->create();

        // Settings seed a limit of 10/day.
        for ($i = 0; $i < 10; $i++) {
            $spent = $this->makeAsset(['is_premium' => false]);
            DB::table('downloads')->insert([
                'user_id'     => $user->id,
                'asset_id'    => $spent->id,
                'access_type' => 'free',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $result = $this->entitlements->check($user, $this->makeAsset(['is_premium' => false]));

        $this->assertFalse($result->allowed);
        $this->assertSame('daily_limit_reached', $result->reason);
    }

    // ---------------------------------------------------------------
    // Premium gating — the core revenue protection
    // ---------------------------------------------------------------

    public function test_premium_asset_is_denied_without_purchase_or_subscription(): void
    {
        $asset = $this->makeAsset(['is_premium' => true, 'price' => 12.0000]);
        $user  = User::factory()->create();

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('purchase_required', $result->reason);
    }

    public function test_premium_asset_is_allowed_after_a_paid_purchase(): void
    {
        $asset = $this->makeAsset(['is_premium' => true, 'price' => 12.0000]);
        $user  = User::factory()->create();

        $this->givePaidOrder($user, $asset);

        $result = $this->entitlements->check($user, $asset);

        $this->assertTrue($result->allowed);
        $this->assertSame('purchase', $result->accessType);
    }

    /**
     * The distinction that matters most: an order that exists but has not
     * been paid must not grant access. Without the status filter, anyone
     * could add to cart, abandon checkout, and download for free.
     */
    public function test_unpaid_order_does_not_grant_access(): void
    {
        $asset = $this->makeAsset(['is_premium' => true, 'price' => 12.0000]);
        $user  = User::factory()->create();

        $this->givePaidOrder($user, $asset, status: 'pending');

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('purchase_required', $result->reason);
    }

    public function test_active_subscription_grants_premium_access(): void
    {
        $asset = $this->makeAsset(['is_premium' => true]);
        $user  = User::factory()->create();

        $this->giveSubscription($user, $this->makePlan(['premium_access' => true]));

        $result = $this->entitlements->check($user, $asset);

        $this->assertTrue($result->allowed);
        $this->assertSame('subscription', $result->accessType);
    }

    public function test_subscription_without_premium_access_is_denied(): void
    {
        $asset = $this->makeAsset(['is_premium' => true]);
        $user  = User::factory()->create();

        $this->giveSubscription($user, $this->makePlan(['premium_access' => false]));

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('plan_excludes_premium', $result->reason);
    }

    public function test_expired_subscription_does_not_grant_access(): void
    {
        $asset = $this->makeAsset(['is_premium' => true]);
        $user  = User::factory()->create();

        $this->giveSubscription(
            $user,
            $this->makePlan(['premium_access' => true]),
            endsAt: now()->subDay(),
        );

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('purchase_required', $result->reason);
    }

    /**
     * A cancelled subscription still inside its paid period keeps working.
     * Cutting access at cancellation rather than period-end takes away
     * something the customer already paid for.
     */
    public function test_cancelled_subscription_still_works_during_grace_period(): void
    {
        $asset = $this->makeAsset(['is_premium' => true]);
        $user  = User::factory()->create();

        $this->giveSubscription(
            $user,
            $this->makePlan(['premium_access' => true]),
            endsAt: now()->addDays(10),
        );

        $this->assertTrue($this->entitlements->check($user, $asset)->allowed);
    }

    public function test_subscription_only_asset_cannot_be_unlocked_by_purchase(): void
    {
        $asset = $this->makeAsset(['is_premium' => true, 'subscription_only' => true]);
        $user  = User::factory()->create();

        $this->givePaidOrder($user, $asset);

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('subscription_required', $result->reason);
    }

    // ---------------------------------------------------------------
    // Asset availability
    // ---------------------------------------------------------------

    public function test_unpublished_asset_is_never_downloadable(): void
    {
        $asset = $this->makeAsset(['status' => 'under_review', 'is_premium' => false]);
        $user  = User::factory()->create();

        $this->givePaidOrder($user, $asset);

        $result = $this->entitlements->check($user, $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('asset_unavailable', $result->reason);
    }

    public function test_scheduled_future_asset_is_not_downloadable_yet(): void
    {
        $asset = $this->makeAsset([
            'is_premium'   => false,
            'published_at' => now()->addWeek(),
        ]);

        $result = $this->entitlements->check(User::factory()->create(), $asset);

        $this->assertFalse($result->allowed);
        $this->assertSame('asset_unavailable', $result->reason);
    }

    // ---------------------------------------------------------------
    // Signed URL behaviour
    // ---------------------------------------------------------------

    public function test_signed_url_is_issued_for_an_entitled_user(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $file  = $this->makeFile($asset);
        $user  = User::factory()->create();

        $url = $this->downloads->createSignedUrl($user, $asset, $file);

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('u='.$user->id, $url);
    }

    public function test_signed_url_is_refused_for_an_unentitled_user(): void
    {
        $asset = $this->makeAsset(['is_premium' => true]);
        $file  = $this->makeFile($asset);
        $user  = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('purchase_required');

        $this->downloads->createSignedUrl($user, $asset, $file);
    }

    public function test_expired_signed_url_is_rejected(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $file  = $this->makeFile($asset);
        $user  = User::factory()->create();

        $url = $this->downloads->createSignedUrl($user, $asset, $file);

        // Past the 5-minute TTL seeded in settings.
        $this->travel(10)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    /**
     * A signed URL is bound to the user it was issued to. Without this,
     * the link is a bearer token — paste it anywhere and the premium file
     * is public until it expires.
     */
    public function test_signed_url_cannot_be_used_by_a_different_account(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $file  = $this->makeFile($asset);

        $owner     = User::factory()->create();
        $bystander = User::factory()->create();

        $url = $this->downloads->createSignedUrl($owner, $asset, $file);

        $this->actingAs($bystander)->get($url)->assertForbidden();
    }

    public function test_file_awaiting_malware_scan_is_not_served(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $file  = $this->makeFile($asset, ['scan_status' => 'pending']);
        $user  = User::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('file_not_available');

        $this->downloads->createSignedUrl($user, $asset, $file);
    }

    public function test_successful_download_is_recorded_and_counters_increment(): void
    {
        $asset = $this->makeAsset(['is_premium' => false]);
        $file  = $this->makeFile($asset);
        $user  = User::factory()->create();

        Storage::disk('private')->put($file->path, 'fake-svg-bytes');

        $url = $this->downloads->createSignedUrl($user, $asset, $file);
        $this->actingAs($user)->get($url)->assertOk();

        $this->assertDatabaseHas('downloads', [
            'user_id'     => $user->id,
            'asset_id'    => $asset->id,
            'access_type' => 'free',
        ]);

        $this->assertSame(1, (int) $asset->fresh()->downloads_count);
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function makeAsset(array $overrides = []): Asset
    {
        $type = AssetType::firstOrCreate(
            ['slug' => 'svg-icon'],
            [
                'name'               => 'SVG Icon',
                'allowed_extensions' => ['svg'],
                'allowed_mimes'      => ['image/svg+xml'],
            ],
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
            'title'          => "Test Asset {$n}",
            'slug'           => "test-asset-{$n}",
            'status'         => 'published',
            'published_at'   => now()->subDay(),
            'is_premium'     => false,
            'price'          => 0,
        ], $overrides));
    }

    private function makeFile(Asset $asset, array $overrides = []): AssetFile
    {
        return AssetFile::create(array_merge([
            'asset_id'      => $asset->id,
            'disk'          => 'private',
            'path'          => "assets/{$asset->id}/source.svg",
            'original_name' => 'source.svg',
            'extension'     => 'svg',
            'mime_type'     => 'image/svg+xml',
            'size_bytes'    => 2048,
            'is_source'     => true,
            'scan_status'   => 'clean',
        ], $overrides));
    }

    private function makePlan(array $overrides = []): Plan
    {
        static $n = 0;
        $n++;

        return Plan::create(array_merge([
            'name'          => "Plan {$n}",
            'slug'          => "plan-{$n}",
            'price_monthly' => 9.0000,
            'price_annual'  => 90.0000,
        ], $overrides));
    }

    private function givePaidOrder(User $user, Asset $asset, string $status = 'paid'): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-'.uniqid(),
            'user_id'      => $user->id,
            'total'        => 12.0000,
            'status'       => $status,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id'   => $orderId,
            'asset_id'   => $asset->id,
            'title'      => $asset->title,
            'unit_price' => 12.0000,
            'line_total' => 12.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function giveSubscription(User $user, Plan $plan, $endsAt = null): void
    {
        $id = DB::table('subscriptions')->insertGetId([
            'user_id'       => $user->id,
            'type'          => $plan->slug,
            'stripe_id'     => 'sub_'.uniqid(),
            'stripe_status' => 'active',
            'ends_at'       => $endsAt,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('subscription_items')->insert([
            'subscription_id' => $id,
            'stripe_id'       => 'si_'.uniqid(),
            'stripe_product'  => 'prod_'.uniqid(),
            'stripe_price'    => 'price_'.uniqid(),
            'quantity'        => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
}
