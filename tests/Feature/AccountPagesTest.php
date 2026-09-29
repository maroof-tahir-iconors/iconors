<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Contributor;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The customer "My Account" area.
 *
 * Render coverage exists for the same reason AdminPagesRenderTest does:
 * `view:cache` compiles Blade without executing it, so a template can cache
 * cleanly and still explode on render. Every new account tab must be added
 * to the provider below.
 *
 * The behavioural tests cover the parts that are easy to get quietly wrong —
 * ownership on delete, and the plan fallback that the header dropdown reads
 * on every authenticated page.
 */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    public static function accountPages(): array
    {
        return [
            // Served at /dashboard, but part of the account shell.
            'overview' => ['dashboard'],
            'collections' => ['account.collections'],
            'downloads' => ['account.downloads'],
            'following' => ['account.following'],
            'api' => ['account.api'],
            'details' => ['account.details'],
            'subscription' => ['account.subscription'],
            'members' => ['account.members'],
        ];
    }

    #[DataProvider('accountPages')]
    public function test_account_page_renders(string $routeName): void
    {
        $response = $this->actingAs($this->customer())->get(route($routeName));

        $response->assertOk();
        // Assert on the shell, so a blank-but-200 response fails too.
        $response->assertSee('account-tabs', false);
        $response->assertSee('My Account');
    }

    #[DataProvider('accountPages')]
    public function test_account_page_requires_authentication(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    /**
     * The stop-impersonating control lives ONLY on this page. When the
     * dashboard moved into the account shell it very nearly went missing,
     * which would strand an administrator inside another user's session.
     */
    public function test_the_overview_shows_the_stop_impersonating_control(): void
    {
        $admin = $this->customer('admin@iconors.test');
        $target = $this->customer('target@iconors.test');

        $this->actingAs($target)
            ->withSession(['impersonator_id' => $admin->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Stop impersonating')
            ->assertSee(route('impersonate.stop'));
    }

    public function test_the_overview_shows_account_totals(): void
    {
        $user = $this->customer();

        Collection::create(['user_id' => $user->id, 'name' => 'One', 'slug' => 'one']);
        Collection::create(['user_id' => $user->id, 'name' => 'Two', 'slug' => 'two']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Collections')
            ->assertSee('No downloads yet');
    }

    public function test_account_root_redirects_to_collections(): void
    {
        $this->actingAs($this->customer())
            ->get('/account')
            ->assertRedirect('/account/collections');
    }

    public function test_user_can_create_a_collection(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->post(route('account.collections.store'), [
                'name' => 'Dashboard icons',
                'description' => 'For the admin rebuild',
            ])
            ->assertRedirect(route('account.collections'));

        $this->assertDatabaseHas('collections', [
            'user_id' => $user->id,
            'name' => 'Dashboard icons',
            'slug' => 'dashboard-icons',
        ]);
    }

    /** Collection slugs are unique table-wide, so a clash must not 500. */
    public function test_duplicate_collection_names_get_distinct_slugs(): void
    {
        $first = $this->customer();
        $second = $this->customer('second@iconors.test');

        $this->actingAs($first)
            ->post(route('account.collections.store'), ['name' => 'Favourites']);
        $this->actingAs($second)
            ->post(route('account.collections.store'), ['name' => 'Favourites']);

        $this->assertDatabaseHas('collections', ['user_id' => $first->id, 'slug' => 'favourites']);
        $this->assertDatabaseHas('collections', ['user_id' => $second->id, 'slug' => 'favourites-2']);
    }

    public function test_collection_name_is_required(): void
    {
        $this->actingAs($this->customer())
            ->post(route('account.collections.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_delete_their_own_collection(): void
    {
        $user = $this->customer();
        $collection = Collection::create([
            'user_id' => $user->id,
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $this->actingAs($user)
            ->delete(route('account.collections.destroy', $collection))
            ->assertRedirect(route('account.collections'));

        $this->assertSoftDeleted('collections', ['id' => $collection->id]);
    }

    /** The bug this guards: auth alone would let anyone delete by id. */
    public function test_user_cannot_delete_someone_elses_collection(): void
    {
        $owner = $this->customer();
        $attacker = $this->customer('attacker@iconors.test');

        $collection = Collection::create([
            'user_id' => $owner->id,
            'name' => 'Private',
            'slug' => 'private',
        ]);

        $this->actingAs($attacker)
            ->delete(route('account.collections.destroy', $collection))
            ->assertForbidden();

        $this->assertDatabaseHas('collections', [
            'id' => $collection->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_update_their_details(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.details.update'), [
                'name' => 'Renamed Person',
                'email' => 'renamed@iconors.test',
            ])
            ->assertRedirect(route('account.details'));

        $user->refresh();

        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('renamed@iconors.test', $user->email);
        // Changing the address must invalidate the old verification.
        $this->assertNull($user->email_verified_at);
    }

    public function test_details_email_must_be_unique(): void
    {
        $this->customer('taken@iconors.test');
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.details.update'), [
                'name' => $user->name,
                'email' => 'taken@iconors.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_user_can_change_their_password(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('account.details'));

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    /**
     * The header dropdown calls this on every authenticated page render, so a
     * null here would blank the plan label site-wide.
     */
    public function test_user_without_a_subscription_falls_back_to_the_free_plan(): void
    {
        $this->assertSame('Free', $this->customer()->currentPlan()?->name);
    }

    public function test_following_tab_lists_followed_contributors_and_can_unfollow(): void
    {
        $user = $this->customer();
        $contributor = Contributor::create([
            'user_id' => $this->customer('maker@iconors.test')->id,
            'display_name' => 'Studio Nine',
            'slug' => 'studio-nine',
            'status' => 'approved',
            'followers_count' => 1,
        ]);

        $user->following()->attach($contributor->id);

        $this->actingAs($user)
            ->get(route('account.following'))
            ->assertOk()
            ->assertSee('Studio Nine');

        $this->actingAs($user)
            ->delete(route('account.following.destroy', $contributor))
            ->assertRedirect(route('account.following'));

        $this->assertDatabaseMissing('contributor_follows', [
            'user_id' => $user->id,
            'contributor_id' => $contributor->id,
        ]);
    }

    public function test_header_dropdown_shows_account_links_when_signed_in(): void
    {
        $response = $this->actingAs($this->customer())->get(route('assets.index'));

        $response->assertOk();
        $response->assertSee('ico-account__menu', false);
        $response->assertSee('My Collections');
        $response->assertSee('Invite Members');
        $response->assertSee('Free Plan');
    }

    public function test_header_shows_sign_up_when_signed_out(): void
    {
        $response = $this->get(route('assets.index'));

        $response->assertOk();
        $response->assertDontSee('ico-account__menu', false);
        $response->assertSee('Sign up');
    }

    private function customer(string $email = 'customer@iconors.test'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
