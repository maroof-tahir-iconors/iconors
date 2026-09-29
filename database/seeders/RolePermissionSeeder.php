<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The 19 default roles and granular permissions from brief §26.
 *
 * "edit own" and "edit all" are deliberately separate permissions rather
 * than one permission plus a runtime ownership check. A contributor holding
 * assets.edit.own can never be widened into assets.edit.all by a bug in a
 * controller conditional — the gate is the grant itself.
 *
 * Roles are re-runnable: syncPermissions replaces rather than appends, so
 * re-seeding after a permission is REMOVED from this file actually revokes it.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($this->roles() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // '*' = every permission. Resolved at seed time so Super
            // Administrator automatically gains permissions added later.
            $role->syncPermissions(
                $permissions === ['*'] ? Permission::all() : $permissions
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissions(): array
    {
        $crud = [
            'assets', 'packs', 'taxonomies', 'users', 'contributors', 'teams',
            'orders', 'subscriptions', 'coupons', 'reviews', 'reports',
            'tickets', 'pages', 'posts', 'menus', 'media',
        ];

        $permissions = [];

        foreach ($crud as $resource) {
            $permissions[] = "{$resource}.view";
            $permissions[] = "{$resource}.create";
            $permissions[] = "{$resource}.edit.own";
            $permissions[] = "{$resource}.edit.all";
            $permissions[] = "{$resource}.delete.own";
            $permissions[] = "{$resource}.delete.all";
            $permissions[] = "{$resource}.export";
        }

        return array_merge($permissions, [
            // Moderation
            'assets.approve', 'assets.reject', 'assets.publish',
            'assets.feature', 'assets.schedule', 'assets.suspend',
            'contributors.approve', 'contributors.reject', 'contributors.suspend',

            // Finance — the highest-risk cluster; never bundled into a
            // generic "admin" grant.
            'payments.manage', 'payouts.manage', 'payouts.approve',
            'refunds.create', 'balances.adjust', 'reports.financial.view',

            // Platform configuration
            'settings.manage', 'branding.manage', 'smtp.manage',
            'seo.manage', 'integrations.manage',

            // Sensitive operations
            'users.impersonate', 'users.anonymize', 'users.force_logout',
            'roles.manage', 'permissions.manage',
            'audit_logs.view', 'analytics.view', 'data.export',
        ]);
    }

    private function roles(): array
    {
        return [
            'Super Administrator' => ['*'],

            // Deliberately NOT '*': cannot manage roles/permissions or read
            // audit logs about themselves. Separation of duty — an admin
            // should not be able to grant themselves finance rights.
            'Administrator' => [
                'assets.view', 'assets.create', 'assets.edit.all', 'assets.delete.all',
                'assets.approve', 'assets.reject', 'assets.publish', 'assets.feature',
                'packs.view', 'packs.create', 'packs.edit.all', 'packs.delete.all',
                'taxonomies.view', 'taxonomies.create', 'taxonomies.edit.all',
                'users.view', 'users.edit.all', 'teams.view', 'teams.edit.all',
                'contributors.view', 'contributors.approve', 'contributors.reject',
                'orders.view', 'subscriptions.view', 'coupons.view', 'coupons.create',
                'reviews.view', 'reports.view', 'tickets.view',
                'pages.view', 'pages.edit.all', 'posts.view', 'posts.edit.all',
                'menus.view', 'menus.edit.all', 'media.view', 'media.create',
                'settings.manage', 'branding.manage', 'seo.manage',
                'analytics.view', 'audit_logs.view',
            ],

            'Asset Manager' => [
                'assets.view', 'assets.create', 'assets.edit.all', 'assets.delete.all',
                'assets.feature', 'assets.schedule', 'assets.publish', 'assets.export',
                'packs.view', 'packs.create', 'packs.edit.all',
                'taxonomies.view', 'taxonomies.create', 'taxonomies.edit.all',
                'media.view', 'media.create',
            ],

            'Asset Reviewer' => [
                'assets.view', 'assets.approve', 'assets.reject', 'assets.suspend',
                'packs.view', 'reports.view',
            ],

            'Contributor Manager' => [
                'contributors.view', 'contributors.edit.all',
                'contributors.approve', 'contributors.reject', 'contributors.suspend',
                'assets.view', 'analytics.view',
            ],

            'Finance Manager' => [
                'orders.view', 'orders.export',
                'subscriptions.view', 'subscriptions.edit.all',
                'payments.manage', 'payouts.manage', 'payouts.approve',
                'refunds.create', 'balances.adjust',
                'reports.financial.view', 'coupons.view', 'coupons.create',
                'analytics.view',
            ],

            'Support Manager' => [
                'tickets.view', 'tickets.create', 'tickets.edit.all', 'tickets.delete.all',
                'users.view', 'orders.view', 'subscriptions.view', 'reports.view',
            ],

            'Support Agent' => [
                'tickets.view', 'tickets.create', 'tickets.edit.own',
                'users.view', 'orders.view',
            ],

            'SEO Manager' => [
                'seo.manage', 'pages.view', 'pages.edit.all',
                'posts.view', 'posts.edit.all', 'analytics.view',
            ],

            'Content Editor' => [
                'pages.view', 'pages.create', 'pages.edit.all',
                'posts.view', 'posts.create', 'posts.edit.all',
                'media.view', 'media.create', 'menus.view',
            ],

            'Marketing Manager' => [
                'coupons.view', 'coupons.create', 'coupons.edit.all',
                'posts.view', 'posts.create', 'posts.edit.all',
                'analytics.view', 'users.export',
            ],

            'Analyst' => [
                'analytics.view', 'reports.view', 'reports.financial.view',
                'orders.view', 'assets.view', 'data.export',
            ],

            'Contributor' => [
                'assets.view', 'assets.create', 'assets.edit.own', 'assets.delete.own',
                'packs.create', 'packs.edit.own',
                'analytics.view', 'tickets.create',
            ],

            'Team Owner' => [
                'teams.view', 'teams.edit.own', 'teams.delete.own',
                'orders.view', 'subscriptions.view', 'payments.manage',
            ],

            'Team Administrator' => ['teams.view', 'teams.edit.own'],
            'Team Member'        => ['teams.view'],
            'Premium Customer'   => [],
            'Free Customer'      => [],

            // Intentionally empty: suspension is enforced by removing every
            // grant, not by a boolean the rest of the app must remember to check.
            'Suspended User'     => [],
        ];
    }
}
