<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\ContributorApplication;
use App\Models\User;
use App\Services\Asset\AssetReviewService;
use App\Services\Contributor\ApplicationService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The contributor chain from brief §43:
 *   - a contributor can apply
 *   - an administrator can review submissions
 *   - an approved contributor can upload assets
 *   - approved assets appear in marketplace search
 */
class ContributorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);

        Storage::fake('private');
        Storage::fake('public');

        AssetType::firstOrCreate(
            ['slug' => 'svg-icon'],
            [
                'name'               => 'SVG Icon',
                'allowed_extensions' => ['svg'],
                'allowed_mimes'      => ['image/svg+xml'],
                'max_file_size_kb'   => 10240,
            ],
        );
    }

    // ---------------------------------------------------------------
    // Application
    // ---------------------------------------------------------------

    public function test_a_user_can_submit_a_contributor_application(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->post(route('contributor.apply.store'), $this->applicationPayload())
            ->assertRedirect(route('contributor.apply.status'));

        $this->assertDatabaseHas('contributor_applications', [
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);
    }

    public function test_application_requires_accepting_the_terms(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->post(route('contributor.apply.store'), $this->applicationPayload(['terms' => null]))
            ->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('contributor_applications', 0);
    }

    /** A double-submitted form must not create two open applications. */
    public function test_a_user_cannot_open_two_applications(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post(route('contributor.apply.store'), $this->applicationPayload());
        $this->actingAs($user)->post(route('contributor.apply.store'), $this->applicationPayload());

        $this->assertDatabaseCount('contributor_applications', 1);
    }

    // ---------------------------------------------------------------
    // Review
    // ---------------------------------------------------------------

    public function test_admin_approval_creates_a_contributor_and_grants_the_role(): void
    {
        $user  = $this->customer();
        $admin = $this->admin();

        $application = ContributorApplication::create(
            $this->applicationPayload(['user_id' => $user->id, 'status' => 'pending'])
        );

        $this->actingAs($admin)
            ->post(route('admin.applications.approve', $application))
            ->assertRedirect();

        $this->assertDatabaseHas('contributors', [
            'user_id' => $user->id,
            'status'  => 'approved',
        ]);

        $this->assertTrue($user->fresh()->hasRole('Contributor'));

        // Approval is a financially and reputationally significant act,
        // so it must leave an audit trail (brief §33).
        $this->assertDatabaseHas('audit_logs', [
            'action'   => 'contributor.approved',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_a_plain_customer_cannot_approve_applications(): void
    {
        $application = ContributorApplication::create(
            $this->applicationPayload(['user_id' => $this->customer()->id, 'status' => 'pending'])
        );

        $this->actingAs($this->customer())
            ->post(route('admin.applications.approve', $application))
            ->assertForbidden();

        $this->assertDatabaseMissing('contributors', ['status' => 'approved']);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $application = ContributorApplication::create(
            $this->applicationPayload(['user_id' => $this->customer()->id, 'status' => 'pending'])
        );

        $this->actingAs($this->admin())
            ->post(route('admin.applications.reject', $application), ['reason' => 'no'])
            ->assertSessionHasErrors('reason');
    }

    // ---------------------------------------------------------------
    // Upload gating
    // ---------------------------------------------------------------

    public function test_a_user_without_a_contributor_record_is_redirected_to_apply(): void
    {
        $this->actingAs($this->customer())
            ->get(route('contributor.assets.create'))
            ->assertRedirect(route('contributor.apply'));
    }

    /**
     * Suspension must bite immediately. The middleware checks contributor
     * status rather than only the role, so an admin does not have to
     * remember to also strip roles when suspending someone.
     */
    public function test_a_suspended_contributor_cannot_reach_the_upload_form(): void
    {
        [$user] = $this->approvedContributor();
        $user->contributor->update(['status' => 'suspended']);

        $this->actingAs($user)
            ->get(route('contributor.assets.create'))
            ->assertRedirect(route('contributor.apply.status'));
    }

    public function test_a_pending_contributor_cannot_upload(): void
    {
        [$user] = $this->approvedContributor();
        $user->contributor->update(['status' => 'pending']);

        $this->actingAs($user)
            ->post(route('contributor.assets.store'), [])
            ->assertRedirect(route('contributor.apply.status'));
    }

    // ---------------------------------------------------------------
    // Upload
    // ---------------------------------------------------------------

    public function test_an_approved_contributor_can_upload_an_asset(): void
    {
        [$user] = $this->approvedContributor();

        $this->actingAs($user)
            ->post(route('contributor.assets.store'), $this->uploadPayload())
            ->assertRedirect(route('contributor.assets.index'));

        $asset = Asset::first();

        $this->assertNotNull($asset);
        $this->assertSame('Test Rocket Icon', $asset->title);

        // Never auto-published: moderation is mandatory (brief §20).
        $this->assertSame('submitted', $asset->status);
        $this->assertNull($asset->published_at);

        // Source lands on the PRIVATE disk, preview on public.
        $file = $asset->files()->first();
        $this->assertSame('private', $file->disk);
        Storage::disk('private')->assertExists($file->path);
    }

    public function test_uploaded_svg_is_sanitised_before_it_is_stored(): void
    {
        [$user] = $this->approvedContributor();

        $hostile = '<svg xmlns="http://www.w3.org/2000/svg">'
                 .'<script>alert(document.cookie)</script>'
                 .'<rect width="10" height="10" onload="steal()"/></svg>';

        $this->actingAs($user)->post(
            route('contributor.assets.store'),
            $this->uploadPayload(['file' => $this->svgFile($hostile)])
        );

        $file  = Asset::first()->files()->first();
        $bytes = Storage::disk('private')->get($file->path);

        // The bytes ON DISK must be clean. Sanitising only at render time
        // would leave every future read path responsible for remembering.
        $this->assertStringNotContainsString('script', $bytes);
        $this->assertStringNotContainsString('onload', $bytes);
        $this->assertStringNotContainsString('alert', $bytes);
    }

    public function test_a_file_whose_extension_does_not_match_the_type_is_rejected(): void
    {
        [$user] = $this->approvedContributor();

        $this->actingAs($user)
            ->post(route('contributor.assets.store'), $this->uploadPayload([
                'file' => UploadedFile::fake()->create('malware.exe', 12, 'application/x-msdownload'),
            ]))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('assets', 0);
    }

    // ---------------------------------------------------------------
    // Asset moderation
    // ---------------------------------------------------------------

    public function test_admin_approval_publishes_the_asset_and_it_appears_in_the_marketplace(): void
    {
        [$user, $contributor] = $this->approvedContributor();
        $this->actingAs($user)->post(route('contributor.assets.store'), $this->uploadPayload());

        $asset = Asset::first();

        // Not visible before approval.
        $this->get(route('assets.index'))->assertDontSee('Test Rocket Icon');

        $this->actingAs($this->admin())
            ->post(route('admin.assets.approve', $asset))
            ->assertRedirect();

        $asset->refresh();
        $this->assertSame('published', $asset->status);
        $this->assertNotNull($asset->published_at);

        // Visible after — this is the §43 criterion "approved assets
        // appear in marketplace search".
        $this->get(route('assets.index'))->assertSee('Test Rocket Icon');
        $this->get(route('assets.index', ['q' => 'Rocket']))->assertSee('Test Rocket Icon');

        $this->assertSame(1, (int) $contributor->fresh()->assets_count);
    }

    public function test_requesting_changes_returns_feedback_to_the_contributor(): void
    {
        [$user] = $this->approvedContributor();
        $this->actingAs($user)->post(route('contributor.assets.store'), $this->uploadPayload());

        $asset = Asset::first();

        $this->actingAs($this->admin())->post(route('admin.assets.changes', $asset), [
            'feedback' => 'Please supply a version with a transparent background.',
        ]);

        $asset->refresh();
        $this->assertSame('changes_requested', $asset->status);
        $this->assertStringContainsString('transparent background', $asset->status_reason);
    }

    public function test_a_rejected_asset_never_becomes_publicly_visible(): void
    {
        [$user] = $this->approvedContributor();
        $this->actingAs($user)->post(route('contributor.assets.store'), $this->uploadPayload());

        $asset = Asset::first();

        $this->actingAs($this->admin())->post(route('admin.assets.reject', $asset), [
            'reason' => 'This artwork appears to be traced from a third-party source.',
        ]);

        $asset->refresh();
        $this->assertSame('rejected', $asset->status);
        $this->assertNull($asset->published_at);
        $this->get(route('assets.index'))->assertDontSee('Test Rocket Icon');
    }

    /**
     * Separation of duty. Without this, any staff member who also
     * contributes becomes their own approver.
     */
    public function test_a_reviewer_cannot_approve_their_own_submission(): void
    {
        $reviewer = $this->admin();

        Contributor::create([
            'user_id'      => $reviewer->id,
            'display_name' => 'Staff Contributor',
            'slug'         => 'staff-contributor',
            'status'       => 'approved',
        ]);

        $this->actingAs($reviewer)->post(route('contributor.assets.store'), $this->uploadPayload());

        $asset = Asset::first();
        $this->assertNotNull($asset);

        $this->actingAs($reviewer)
            ->post(route('admin.assets.approve', $asset))
            ->assertForbidden();

        $this->assertSame('submitted', $asset->fresh()->status);
    }

    /**
     * Regression. The moderation queue was originally gated on
     * `assets.view`, which contributors legitimately hold for their own
     * work — so they could load the queue and read every other
     * contributor's unpublished submissions. They could not act on them,
     * but the listing itself was the disclosure. Moderation screens must
     * be gated on a moderation permission.
     */
    public function test_a_contributor_cannot_even_view_the_moderation_queue(): void
    {
        [$owner] = $this->approvedContributor();
        $this->actingAs($owner)->post(route('contributor.assets.store'), $this->uploadPayload());

        [$nosy] = $this->approvedContributor('nosy@iconors.test', 'Nosy Maker');

        $this->actingAs($nosy)
            ->get(route('admin.assets.review'))
            ->assertForbidden();

        $this->actingAs($nosy)
            ->get(route('admin.applications'))
            ->assertForbidden();
    }

    public function test_an_asset_reviewer_can_view_the_moderation_queue(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('Asset Reviewer');

        $this->actingAs($reviewer)
            ->get(route('admin.assets.review'))
            ->assertOk();

        // …but reviewing assets does not confer authority over contributor
        // applications. Separate duties, separate permissions.
        $this->actingAs($reviewer)
            ->get(route('admin.applications'))
            ->assertForbidden();
    }

    public function test_a_contributor_cannot_moderate_assets(): void
    {
        [$owner] = $this->approvedContributor();
        $this->actingAs($owner)->post(route('contributor.assets.store'), $this->uploadPayload());

        $asset = Asset::first();

        [$other] = $this->approvedContributor('other@iconors.test', 'Other Maker');

        $this->actingAs($other)
            ->post(route('admin.assets.approve', $asset))
            ->assertForbidden();

        $this->assertSame('submitted', $asset->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Free Customer');

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Administrator');

        return $user;
    }

    /** @return array{0: User, 1: Contributor} */
    private function approvedContributor(string $email = null, string $name = 'Test Maker'): array
    {
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->assignRole('Contributor');

        $application = ContributorApplication::create(
            $this->applicationPayload(['user_id' => $user->id, 'display_name' => $name, 'status' => 'pending'])
        );

        $contributor = app(ApplicationService::class)->approve($application, $this->admin());

        return [$user->fresh(), $contributor];
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name'     => 'Test Maker',
            'display_name'  => 'Test Maker',
            'country'       => 'GB',
            'biography'     => 'I design icon sets and animated illustrations for product teams.',
            'portfolio_url' => 'https://example.com/portfolio',
            'specialties'   => ['Icons'],
            'terms'         => '1',
        ], $overrides);
    }

    private function uploadPayload(array $overrides = []): array
    {
        return array_merge([
            'file'          => $this->svgFile(),
            'title'         => 'Test Rocket Icon',
            'description'   => 'A rocket icon for launch and startup themes.',
            'asset_type_id' => AssetType::where('slug', 'svg-icon')->value('id'),
            'tags'          => 'rocket, launch, startup',
        ], $overrides);
    }

    private function svgFile(?string $contents = null): UploadedFile
    {
        $contents ??= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
                    .'<path d="M12 2L2 7l10 5 10-5z" fill="#3CF7A2"/></svg>';

        $path = tempnam(sys_get_temp_dir(), 'ico').'.svg';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'rocket.svg', 'image/svg+xml', null, true);
    }
}
