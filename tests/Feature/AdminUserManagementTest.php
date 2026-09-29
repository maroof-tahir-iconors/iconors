<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * User administration — brief §25, §26, §33.
 *
 * This is the highest-risk admin surface in the product: it can change
 * who holds which powers. The tests below are mostly about what an
 * administrator must NOT be able to do.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    // ---------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------

    public function test_a_customer_cannot_reach_user_administration(): void
    {
        $this->actingAs($this->withRole('Free Customer'))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_a_contributor_cannot_reach_user_administration(): void
    {
        $this->actingAs($this->withRole('Contributor'))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    /**
     * Regression on a grouping mistake made twice before: support staff
     * hold `users.view` but not `assets.approve`. Gating user admin behind
     * the moderation group would lock them out of records they need.
     */
    public function test_a_support_manager_can_reach_user_administration(): void
    {
        $this->actingAs($this->withRole('Support Manager'))
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    // ---------------------------------------------------------------
    // Privilege escalation
    // ---------------------------------------------------------------

    /**
     * Separation of duty: role management is reserved to Super
     * Administrator. A plain Administrator can manage users but cannot
     * hand out authority, so they are stopped at the gate, not the guard.
     */
    public function test_a_plain_administrator_cannot_manage_roles_at_all(): void
    {
        $admin  = $this->withRole('Administrator');
        $target = $this->withRole('Free Customer');

        $this->actingAs($admin)
            ->post(route('admin.users.roles', $target), ['roles' => ['Super Administrator']])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('Super Administrator'));
    }

    /**
     * The single most important test here. Even the highest-privileged
     * account must not edit its own roles — otherwise a compromised admin
     * session is a permanent one, and demotion can be self-reverted.
     */
    public function test_even_a_super_administrator_cannot_change_their_own_roles(): void
    {
        $admin = $this->withRole('Super Administrator');

        $this->actingAs($admin)
            ->post(route('admin.users.roles', $admin), ['roles' => ['Free Customer']])
            ->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()->hasRole('Super Administrator'));
    }

    /**
     * A role manager may only hand out authority they already hold.
     * Uses a bespoke grant so we have someone with `roles.manage` who is
     * NOT a Super Administrator — the escalation case that matters.
     */
    public function test_a_role_manager_cannot_grant_a_role_they_do_not_hold(): void
    {
        $manager = $this->withRole('Support Manager');
        $manager->givePermissionTo('roles.manage');

        $target = $this->withRole('Free Customer');

        $this->actingAs($manager)
            ->post(route('admin.users.roles', $target), ['roles' => ['Finance Manager']])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($target->fresh()->hasRole('Finance Manager'));

        // …but a role they DO hold is fine.
        $this->actingAs($manager)
            ->post(route('admin.users.roles', $target), ['roles' => ['Support Manager']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('Support Manager'));
    }

    public function test_a_super_administrator_can_grant_any_role(): void
    {
        $target = $this->withRole('Free Customer');

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.roles', $target), ['roles' => ['Finance Manager']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasRole('Finance Manager'));
    }

    public function test_role_changes_are_audited_with_before_and_after(): void
    {
        $target = $this->withRole('Free Customer');
        $admin  = $this->withRole('Super Administrator');

        $this->actingAs($admin)->post(route('admin.users.roles', $target), ['roles' => ['Premium Customer']]);

        $log = DB::table('audit_logs')->where('action', 'user.roles_changed')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertStringContainsString('Free Customer', (string) $log->old_values);
        $this->assertStringContainsString('Premium Customer', (string) $log->new_values);
    }

    // ---------------------------------------------------------------
    // Suspension
    // ---------------------------------------------------------------

    public function test_suspending_strips_every_role_and_clears_sessions(): void
    {
        $target = $this->withRole('Contributor');

        DB::table('sessions')->insert([
            'id'            => 'sess-'.uniqid(),
            'user_id'       => $target->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'test',
            'payload'       => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.suspend', $target), ['reason' => 'Repeated licence violations.'])
            ->assertSessionHasNoErrors();

        $target->refresh();

        // Suspension removes grants rather than setting a flag, so no other
        // part of the app has to remember to check one.
        $this->assertTrue($target->hasRole('Suspended User'));
        $this->assertFalse($target->hasRole('Contributor'));

        // A suspended user with a live session is still logged in.
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_suspension_requires_a_reason(): void
    {
        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.suspend', $this->withRole('Free Customer')), ['reason' => 'bad'])
            ->assertSessionHasErrors('reason');
    }

    public function test_an_admin_cannot_suspend_themselves(): void
    {
        $admin = $this->withRole('Super Administrator');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $admin), ['reason' => 'Locking myself out by accident.'])
            ->assertSessionHasErrors('user');

        $this->assertFalse($admin->fresh()->hasRole('Suspended User'));
    }

    public function test_a_suspended_user_can_be_restored(): void
    {
        $target = $this->withRole('Suspended User');

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.restore', $target), ['role' => 'Free Customer'])
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertTrue($target->hasRole('Free Customer'));
        $this->assertFalse($target->hasRole('Suspended User'));
    }

    // ---------------------------------------------------------------
    // Impersonation
    // ---------------------------------------------------------------

    public function test_an_admin_can_impersonate_a_customer_and_return(): void
    {
        $admin    = $this->withRole('Super Administrator');
        $customer = $this->withRole('Free Customer');

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($customer->id, auth()->id());
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertSame($admin->id, auth()->id());
    }

    /**
     * Impersonating staff would be a straightforward escalation path: a
     * user manager could become a finance manager just by borrowing them.
     */
    public function test_staff_accounts_cannot_be_impersonated(): void
    {
        $finance = $this->withRole('Finance Manager');

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.impersonate', $finance))
            ->assertSessionHasErrors('user');
    }

    public function test_impersonation_is_audited(): void
    {
        $customer = $this->withRole('Free Customer');

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.impersonate', $customer));

        $this->assertDatabaseHas('audit_logs', [
            'action'      => 'user.impersonated',
            'target_id'   => $customer->id,
        ]);
    }

    public function test_a_support_agent_cannot_impersonate(): void
    {
        $this->actingAs($this->withRole('Support Agent'))
            ->post(route('admin.users.impersonate', $this->withRole('Free Customer')))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Other actions
    // ---------------------------------------------------------------

    public function test_forcing_a_password_reset_invalidates_the_password_and_sessions(): void
    {
        $target   = $this->withRole('Free Customer');
        $original = $target->password;

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.password', $target));

        $this->assertNotSame($original, $target->fresh()->password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_an_admin_can_mark_an_account_verified(): void
    {
        $target = $this->withRole('Free Customer');
        $target->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->withRole('Super Administrator'))
            ->post(route('admin.users.verify', $target));

        $this->assertNotNull($target->fresh()->email_verified_at);
    }

    public function test_the_user_list_can_be_searched_and_filtered(): void
    {
        $this->withRole('Free Customer')->forceFill(['name' => 'Findable Person', 'email' => 'findable@example.com'])->save();
        $this->withRole('Contributor')->forceFill(['name' => 'Someone Else', 'email' => 'other@example.com'])->save();

        $admin = $this->withRole('Super Administrator');

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Findable']))
            ->assertOk()->assertSee('Findable Person')->assertDontSee('Someone Else');

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'Contributor']))
            ->assertOk()->assertSee('Someone Else');
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
