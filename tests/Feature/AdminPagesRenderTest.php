<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Renders every admin page as a super administrator.
 *
 * This exists because `artisan view:cache` is NOT a safety net: it compiles
 * Blade to PHP but never executes it. A template can compile "successfully"
 * and still emit broken PHP that only explodes on render — which is exactly
 * what happened when an inline `@php(...)` produced an unterminated `<?php(`
 * and silently stopped the compiler mid-file.
 *
 * Every new admin screen must be added here.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    #[DataProvider('adminPages')]
    public function test_admin_page_renders(string $routeName): void
    {
        $response = $this->actingAs($this->superAdmin())->get(route($routeName));

        $response->assertOk();
        // A Blade parse error surfaces as a 500 with this text; assert on the
        // rendered shell so a blank-but-200 response also fails.
        $response->assertSee('admin-sidebar', false);
    }

    public static function adminPages(): array
    {
        return [
            'dashboard'          => ['admin.dashboard'],
            'asset review'       => ['admin.assets.review'],
            'applications'       => ['admin.applications'],
            'users index'        => ['admin.users.index'],
            'plans index'        => ['admin.plans.index'],
            'plans create'       => ['admin.plans.create'],
            'licences index'     => ['admin.licenses.index'],
            'licences create'    => ['admin.licenses.create'],
            'settings general'   => ['admin.settings.general'],
            'settings branding'  => ['admin.settings.branding'],
            'settings seo'       => ['admin.settings.seo'],
            'settings payments'  => ['admin.settings.payments'],
            'settings royalties' => ['admin.settings.royalties'],
            'settings market'    => ['admin.settings.marketplace'],
            'settings mail'      => ['admin.settings.mail'],
        ];
    }

    /** The edit screens take a model, so they get their own cases. */
    public function test_the_plan_edit_page_renders(): void
    {
        $plan = Plan::create([
            'name' => 'Render Test', 'slug' => 'render-test',
            'price_monthly' => 9, 'price_annual' => 90,
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee('admin-sidebar', false);
    }

    public function test_the_licence_edit_page_renders(): void
    {
        $license = License::create([
            'name' => 'Render Licence', 'slug' => 'render-licence',
            'summary' => 'Short summary.', 'full_text' => 'Full terms.',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.licenses.edit', $license))
            ->assertOk()
            ->assertSee('admin-sidebar', false);
    }

    public function test_the_user_detail_page_renders(): void
    {
        $target = User::factory()->create();
        $target->assignRole('Free Customer');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('admin-sidebar', false);
    }

    /** Public pages get the same treatment. */
    public function test_public_pages_render(): void
    {
        foreach ([
            route('home'), route('assets.index'), route('login'), route('register'),
            route('pricing'), route('about'), route('careers'), route('blog'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    /** CMS pages seeded by ContentSeeder — legal copy must be reachable. */
    public function test_cms_pages_render(): void
    {
        $this->seed(\Database\Seeders\ContentSeeder::class);

        foreach (['about', 'careers', 'license', 'terms', 'privacy', 'refunds'] as $slug) {
            $this->get(route('page', $slug))->assertOk();
        }
    }

    public function test_a_blog_post_renders(): void
    {
        $this->seed(\Database\Seeders\ContentSeeder::class);

        $post = \App\Models\Post::published()->firstOrFail();

        $this->get(route('blog.post', $post->slug))
            ->assertOk()
            ->assertSee($post->title, false);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        return $user;
    }
}
