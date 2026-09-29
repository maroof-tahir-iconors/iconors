<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How asset previews render in listings.
 *
 * Regression: Lottie previews are .json files, and the card rendered every
 * preview in an <img>. A .json in an <img> cannot decode, so an entire
 * listing of animations came out as broken images. Lottie must render
 * through the player container instead.
 */
class AssetPreviewRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_a_lottie_preview_renders_a_player_not_an_image(): void
    {
        $asset = $this->makeAsset('Waving Hand', 'lottie-animation');

        AssetPreview::create([
            'asset_id'   => $asset->id,
            'type'       => 'lottie',
            'disk'       => 'public',
            'path'       => 'imported/previews/waving.json',
            'is_primary' => true,
        ]);

        $response = $this->get(route('assets.index'));

        $response->assertSee('data-ico-lottie', false);
        // The decisive assertion: no <img> pointing at the JSON.
        $response->assertDontSee('<img src="'.asset('storage/imported/previews/waving.json'), false);
    }

    public function test_a_raster_preview_still_renders_an_image(): void
    {
        $asset = $this->makeAsset('Rocket Icon', 'svg-icon');

        AssetPreview::create([
            'asset_id'   => $asset->id,
            'type'       => 'medium',
            'disk'       => 'public',
            'path'       => 'imported/previews/rocket.svg',
            'is_primary' => true,
        ]);

        $this->get(route('assets.index'))
            ->assertSee('rocket.svg', false)
            ->assertSee('<img', false);
    }

    /**
     * A .json path must be treated as Lottie even when the preview row was
     * created with the wrong type — the importer and the upload path set it
     * differently, and a mismatch here is what produced broken images.
     */
    public function test_a_json_path_is_treated_as_lottie_regardless_of_type(): void
    {
        $asset = $this->makeAsset('Mislabelled Animation', 'lottie-animation');

        AssetPreview::create([
            'asset_id'   => $asset->id,
            'type'       => 'medium',   // deliberately wrong
            'disk'       => 'public',
            'path'       => 'imported/previews/mislabelled.json',
            'is_primary' => true,
        ]);

        $this->get(route('assets.index'))->assertSee('data-ico-lottie', false);
    }

    public function test_an_asset_with_no_preview_renders_a_placeholder(): void
    {
        $this->makeAsset('No Preview Asset', 'svg-icon');

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('bi-image', false);
    }

    private function makeAsset(string $title, string $typeSlug): Asset
    {
        $type = AssetType::firstOrCreate(
            ['slug' => $typeSlug],
            [
                'name'               => str($typeSlug)->headline()->toString(),
                'allowed_extensions' => ['svg', 'json'],
                'allowed_mimes'      => ['image/svg+xml', 'application/json'],
            ],
        );

        $contributor = Contributor::firstOrCreate(
            ['slug' => 'preview-contributor'],
            [
                'user_id'      => User::factory()->create()->id,
                'display_name' => 'Preview Contributor',
                'status'       => 'approved',
            ],
        );

        return Asset::create([
            'contributor_id' => $contributor->id,
            'asset_type_id'  => $type->id,
            'title'          => $title,
            'slug'           => str($title)->slug()->toString(),
            'status'         => 'published',
            'published_at'   => now()->subDay(),
        ]);
    }
}
