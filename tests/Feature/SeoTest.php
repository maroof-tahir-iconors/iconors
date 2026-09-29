<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SEO, structured data, sitemaps and analytics — brief §28, §29.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    // ---------------------------------------------------------------
    // robots.txt
    // ---------------------------------------------------------------

    public function test_robots_txt_is_served_and_points_at_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    /** Private and transactional areas must never be crawled. */
    public function test_robots_txt_disallows_private_areas(): void
    {
        $response = $this->get('/robots.txt');

        foreach (['/admin', '/dashboard', '/contributor', '/download', '/checkout'] as $path) {
            $response->assertSee("Disallow: {$path}", false);
        }
    }

    /**
     * The single switch that de-indexes a staging site. Getting this wrong
     * is how a staging copy outranks production.
     */
    public function test_disabling_indexing_blocks_everything(): void
    {
        app(SettingsService::class)->set('seo_allow_indexing', false);

        $robots = $this->get('/robots.txt');
        $robots->assertSee('Disallow: /', false);
        $robots->assertDontSee('Allow: /', false);

        // …and every page must say so too, not just robots.txt.
        $this->get(route('home'))->assertSee('noindex, nofollow', false);
    }

    // ---------------------------------------------------------------
    // Sitemaps
    // ---------------------------------------------------------------

    public function test_the_sitemap_index_lists_the_child_sitemaps(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<sitemapindex', false);
        $response->assertSee(url('/sitemap-pages.xml'), false);
        $response->assertSee(url('/sitemap-assets.xml'), false);
    }

    public function test_the_asset_sitemap_lists_published_assets_only(): void
    {
        $published = $this->makeAsset(['title' => 'Indexed Icon', 'slug' => 'indexed-icon']);
        $this->makeAsset(['title' => 'Hidden Icon', 'slug' => 'hidden-icon', 'status' => 'submitted', 'published_at' => null]);

        $response = $this->get('/sitemap-assets.xml');

        $response->assertOk();
        $response->assertSee(route('assets.show', $published->slug), false);
        // A draft in the sitemap sends crawlers to a 404 and burns crawl budget.
        $response->assertDontSee('hidden-icon', false);
    }

    public function test_a_disabled_sitemap_section_returns_404(): void
    {
        app(SettingsService::class)->set('seo_sitemap_assets', false);

        $this->get('/sitemap-assets.xml')->assertNotFound();
        $this->get('/sitemap-pages.xml')->assertOk();
    }

    public function test_an_unknown_sitemap_type_is_not_routable(): void
    {
        $this->get('/sitemap-secrets.xml')->assertNotFound();
    }

    public function test_disabling_sitemaps_entirely_returns_404(): void
    {
        app(SettingsService::class)->set('seo_sitemap_enabled', false);

        $this->get('/sitemap.xml')->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Structured data
    // ---------------------------------------------------------------

    public function test_organisation_and_website_structured_data_is_emitted(): void
    {
        $response = $this->get(route('home'));

        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"WebSite"', false);
        // SearchAction is what enables a sitelinks search box in results.
        $response->assertSee('"@type":"SearchAction"', false);
    }

    public function test_the_canonical_url_is_present(): void
    {
        $this->get(route('home'))->assertSee('rel="canonical"', false);
    }

    // ---------------------------------------------------------------
    // Analytics
    // ---------------------------------------------------------------

    /**
     * Brief §28 is explicit: a disabled tracker must not load at all —
     * not merely be present and inert.
     */
    public function test_a_disabled_tracker_emits_nothing(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('analytics_ga_id', 'G-ABC123DEF');
        $settings->set('analytics_ga_enabled', false);

        $response = $this->get(route('home'));

        $response->assertDontSee('googletagmanager.com/gtag', false);
        $response->assertDontSee('G-ABC123DEF', false);
    }

    public function test_an_enabled_tracker_is_emitted(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('analytics_ga_id', 'G-ABC123DEF');
        $settings->set('analytics_ga_enabled', true);

        $this->get(route('home'))->assertSee('G-ABC123DEF', false);
    }

    /**
     * Analytics IDs are interpolated into inline <script> blocks, so a
     * free-text value here would be script injection on every page.
     */
    public function test_a_malformed_analytics_id_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.seo'), $this->seoPayload([
                'analytics_ga_id' => "G-X');alert(1);//",
            ]))
            ->assertSessionHasErrors('analytics_ga_id');
    }

    public function test_a_tracker_cannot_be_enabled_without_an_id(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.seo'), $this->seoPayload([
                'analytics_meta_enabled'  => '1',
                'analytics_meta_pixel_id' => '',
            ]))
            ->assertSessionHasErrors('analytics_meta_enabled');
    }

    // ---------------------------------------------------------------
    // Admin screen
    // ---------------------------------------------------------------

    public function test_seo_settings_can_be_saved(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.seo'), $this->seoPayload([
                'seo_meta_description' => 'A brand new description for the marketplace.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'A brand new description for the marketplace.',
            app(SettingsService::class)->get('seo_meta_description')
        );
    }

    public function test_turning_indexing_off_warns_the_administrator(): void
    {
        $payload = $this->seoPayload();
        unset($payload['seo_allow_indexing']);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.seo'), $payload)
            ->assertSessionHas('warning');
    }

    public function test_only_seo_managers_may_change_seo_settings(): void
    {
        foreach (['Free Customer', 'Contributor', 'Asset Reviewer', 'Finance Manager'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get(route('admin.settings.seo'))->assertForbidden();
        }

        $seo = User::factory()->create();
        $seo->assignRole('SEO Manager');
        $this->actingAs($seo)->get(route('admin.settings.seo'))->assertOk();
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

    private function makeAsset(array $overrides = []): Asset
    {
        $type = AssetType::firstOrCreate(
            ['slug' => 'svg-icon'],
            ['name' => 'SVG Icon', 'allowed_extensions' => ['svg'], 'allowed_mimes' => ['image/svg+xml']],
        );

        $contributor = Contributor::firstOrCreate(
            ['slug' => 'seo-contributor'],
            [
                'user_id'      => User::factory()->create()->id,
                'display_name' => 'SEO Contributor',
                'status'       => 'approved',
                'assets_count' => 1,
            ],
        );

        return Asset::create(array_merge([
            'contributor_id' => $contributor->id,
            'asset_type_id'  => $type->id,
            'title'          => 'SEO Asset',
            'slug'           => 'seo-asset',
            'status'         => 'published',
            'published_at'   => now()->subDay(),
        ], $overrides));
    }

    private function seoPayload(array $overrides = []): array
    {
        return array_merge([
            'seo_title_format'      => '{page} — {site}',
            'seo_default_title'     => 'Iconors',
            'seo_meta_description'  => 'Download high-quality icons and animations.',
            'seo_twitter_card'      => 'summary_large_image',
            'seo_organisation_name' => 'Iconors',
            'seo_allow_indexing'    => '1',
            'seo_sitemap_enabled'   => '1',
            '_fields'               => 'seo_allow_indexing,seo_sitemap_enabled,seo_sitemap_assets,'
                                      .'seo_sitemap_categories,seo_sitemap_contributors,'
                                      .'analytics_ga_enabled,analytics_gtm_enabled,analytics_meta_enabled,'
                                      .'analytics_clarity_enabled,analytics_plausible_enabled',
        ], $overrides);
    }
}
