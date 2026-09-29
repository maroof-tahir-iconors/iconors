<?php

namespace Database\Seeders;

use App\Models\ContributorApplication;
use App\Models\User;
use App\Services\Contributor\ApplicationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local demo accounts so each role can be exercised in a browser.
 *
 * Passwords are intentionally weak placeholders and this seeder is for
 * LOCAL USE ONLY — never run it against a production database. It is not
 * registered in DatabaseSeeder for exactly that reason; run it explicitly:
 *   php artisan db:seed --class=DemoUsersSeeder
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoUsersSeeder must never run in production.');

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@iconors.test'],
            ['name' => 'Iconors Admin', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $admin->syncRoles(['Super Administrator']);

        $reviewer = User::firstOrCreate(
            ['email' => 'reviewer@iconors.test'],
            ['name' => 'Asset Reviewer', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $reviewer->syncRoles(['Asset Reviewer']);

        $maker = User::firstOrCreate(
            ['email' => 'maker@iconors.test'],
            ['name' => 'Demo Maker', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        if (! $maker->contributor) {
            $application = ContributorApplication::create([
                'user_id'           => $maker->id,
                'full_name'         => 'Demo Maker',
                'display_name'      => 'Demo Maker',
                'country'           => 'GB',
                'biography'         => 'Demo contributor account for local testing of the upload workflow.',
                'portfolio_url'     => 'https://example.com/demo',
                'specialties'       => ['Icons', 'Illustrations'],
                'terms_accepted'    => true,
                'terms_accepted_at' => now(),
                'status'            => 'pending',
            ]);

            app(ApplicationService::class)->approve($application, $admin);
        }

        $applicant = User::firstOrCreate(
            ['email' => 'applicant@iconors.test'],
            ['name' => 'Pending Applicant', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $applicant->syncRoles(['Free Customer']);

        // Leaves one application in the queue so the admin review screen
        // has something to act on.
        ContributorApplication::firstOrCreate(
            ['user_id' => $applicant->id],
            [
                'full_name'         => 'Pending Applicant',
                'display_name'      => 'Pending Applicant',
                'country'           => 'US',
                'biography'         => 'I make isometric 3D icons and would love to contribute to Iconors.',
                'portfolio_url'     => 'https://example.com/pending',
                'specialties'       => ['3D'],
                'terms_accepted'    => true,
                'terms_accepted_at' => now(),
                'status'            => 'pending',
            ],
        );

        $financeManager = User::firstOrCreate(
            ['email' => 'finance@iconors.test'],
            ['name' => 'Finance Manager', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $financeManager->syncRoles(['Finance Manager']);

        $customer = User::firstOrCreate(
            ['email' => 'customer@iconors.test'],
            ['name' => 'Demo Customer', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $customer->syncRoles(['Free Customer']);

        // The catalogue seeder creates six contributor users with random
        // factory passwords and no roles, and `iconors:import` creates
        // studio@ with a deliberately unguessable one. All of them own
        // assets but cannot be logged into, which makes the contributor
        // dashboard impossible to inspect. Normalise them for local use.
        $demoContributors = User::where('email', 'like', 'contributor%@iconors.test')
            ->orWhere('email', 'studio@iconors.test')
            ->get();

        foreach ($demoContributors as $demoContributor) {
            $demoContributor->forceFill([
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ])->save();

            $demoContributor->syncRoles(['Contributor']);
        }

        // Throwaway accounts left behind by end-to-end HTTP checks.
        User::where('email', 'like', 'e2e\_%@iconors.test')
            ->orWhere('email', 'like', 'flow\_%@iconors.test')
            ->delete();

        $this->command?->info('Demo accounts — password for ALL of them is: password');
        $this->command?->line('  admin@iconors.test          Super Administrator (everything)');
        $this->command?->line('  finance@iconors.test        Finance Manager (payments, payouts, royalties)');
        $this->command?->line('  reviewer@iconors.test       Asset Reviewer (moderation queue only)');
        $this->command?->line('  maker@iconors.test          approved Contributor (can upload)');
        $this->command?->line('  applicant@iconors.test      pending application (awaiting review)');
        $this->command?->line('  customer@iconors.test       Free Customer');
        $this->command?->line('  studio@iconors.test         Contributor — owns the imported catalogue');
        $this->command?->line('  contributor1..6@iconors.test  Contributors who own the demo catalogue');
    }
}
