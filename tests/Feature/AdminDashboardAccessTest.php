<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may see the admin overview — brief §24, §26.
 *
 * The dashboard aggregates platform-wide revenue, user totals and every
 * moderation queue, so its gate must use STAFF-EXCLUSIVE permissions.
 * Twice now a widely-held `*.view` permission has leaked an admin screen;
 * these tests exist so it cannot happen a third time silently.
 */
class AdminDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    /**
     * Regression. Contributors hold `assets.view` (own uploads) AND
     * `analytics.view` (own earnings). Gating the dashboard on either let
     * them read platform revenue and total user counts.
     */
    public function test_a_contributor_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Contributor'))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Free Customer'))
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Premium Customer'))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    #[DataProvider('staffRoles')]
    public function test_staff_roles_can_open_the_admin_dashboard(string $role): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public static function staffRoles(): array
    {
        return [
            'super admin'         => ['Super Administrator'],
            'administrator'       => ['Administrator'],
            'asset reviewer'      => ['Asset Reviewer'],
            'finance manager'     => ['Finance Manager'],
            'contributor manager' => ['Contributor Manager'],
            'support manager'     => ['Support Manager'],
        ];
    }

    /**
     * The sidebar is filtered by permission so staff are not shown links
     * they cannot use. Presentation only — the routes themselves are what
     * actually enforce this (brief §26: never rely on hiding buttons).
     */
    public function test_the_sidebar_only_shows_sections_the_user_can_use(): void
    {
        $finance = $this->actingAs($this->userWithRole('Finance Manager'))
            ->get(route('admin.dashboard'));

        $finance->assertSee('Payments', false);
        $finance->assertSee('Royalties', false);
        // Finance has no moderation authority.
        $finance->assertDontSee('<span>Asset review</span>', false);
        $finance->assertDontSee('<span>Applications</span>', false);

        $reviewer = $this->actingAs($this->userWithRole('Asset Reviewer'))
            ->get(route('admin.dashboard'));

        $reviewer->assertSee('Asset review', false);
        $reviewer->assertDontSee('<span>Payments</span>', false);
        $reviewer->assertDontSee('<span>Royalties</span>', false);
    }

    /**
     * Hiding the link is not the control. Even with the sidebar filtered,
     * the route must refuse a direct request.
     */
    public function test_hiding_a_link_is_not_the_control(): void
    {
        $reviewer = $this->userWithRole('Asset Reviewer');

        $this->actingAs($reviewer)->get(route('admin.settings.payments'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('admin.settings.royalties'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('admin.plans.index'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
