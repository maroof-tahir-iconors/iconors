<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Seeder;

/**
 * Starter subscription plans — brief §22.
 *
 * These are DEFAULTS, not fixed pricing. Every field here is editable at
 * Admin → Plans, and nothing in the application reads a hard-coded price.
 * They exist so the pricing page has something to render before an
 * operator has configured their own.
 *
 * Note the download-limit convention: NULL means unlimited, 0 means none.
 * Those are different statements and the app relies on the distinction.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $standard = License::where('slug', 'standard-licence')->first()
            ?? License::where('is_active', true)->first();

        foreach ($this->plans() as $definition) {
            $features = $definition['features'];
            unset($definition['features']);

            $plan = Plan::updateOrCreate(
                ['slug' => $definition['slug']],
                $definition + ['license_id' => $standard?->id],
            );

            PlanFeature::where('plan_id', $plan->id)->delete();

            foreach ($features as $i => $feature) {
                PlanFeature::create([
                    'plan_id'        => $plan->id,
                    'label'          => is_array($feature) ? $feature[0] : $feature,
                    'is_included'    => is_array($feature) ? $feature[1] : true,
                    'is_highlighted' => is_array($feature) ? ($feature[2] ?? false) : false,
                    'sort_order'     => $i,
                ]);
            }
        }
    }

    private function plans(): array
    {
        return [
            [
                'slug'  => 'free',
                'name'  => 'Free',
                'description' => 'For trying things out and personal projects.',
                'price_monthly' => 0, 'price_annual' => 0, 'currency' => 'USD',
                'trial_days' => 0,
                'download_limit_daily' => 10,
                'credits_monthly' => 0, 'team_seats' => 1,
                'premium_access' => false, 'source_file_access' => false,
                'editor_access' => true, 'api_access' => false,
                'is_active' => true, 'is_public' => true, 'sort_order' => 0,
                'features' => [
                    '10 free downloads a day',
                    'Full icon and animation library browsing',
                    'Online SVG and Lottie editors',
                    ['Premium assets', false],
                    ['Source files', false],
                ],
            ],
            [
                'slug'  => 'individual-monthly',
                'name'  => 'Individual',
                'description' => 'For designers and developers working solo.',
                'price_monthly' => 12, 'price_annual' => 108, 'currency' => 'USD',
                'trial_days' => 7,
                // NULL = unlimited, deliberately.
                'download_limit_daily' => null,
                'credits_monthly' => 0, 'team_seats' => 1,
                'premium_access' => true, 'source_file_access' => true,
                'editor_access' => true, 'api_access' => false,
                'is_featured' => true,
                'is_active' => true, 'is_public' => true, 'sort_order' => 1,
                'features' => [
                    ['Unlimited downloads', true, true],
                    ['Every premium asset', true, true],
                    'Editable source files',
                    'Commercial licence included',
                    '7-day free trial',
                    ['API access', false],
                ],
            ],
            [
                'slug'  => 'team',
                'name'  => 'Team',
                'description' => 'For studios and product teams sharing a library.',
                'price_monthly' => 39, 'price_annual' => 348, 'currency' => 'USD',
                'trial_days' => 14,
                'download_limit_daily' => null,
                'credits_monthly' => 0, 'team_seats' => 5,
                'premium_access' => true, 'source_file_access' => true,
                'editor_access' => true, 'api_access' => true,
                'is_team_plan' => true,
                'is_active' => true, 'is_public' => true, 'sort_order' => 2,
                'features' => [
                    ['Everything in Individual', true, true],
                    ['5 seats included', true, true],
                    'Shared team collections',
                    'Consolidated billing and invoices',
                    'API access',
                    '14-day free trial',
                ],
            ],
            [
                'slug'  => 'enterprise',
                'name'  => 'Enterprise',
                'description' => 'For larger organisations with bespoke needs.',
                'price_monthly' => 0, 'price_annual' => 0, 'currency' => 'USD',
                'trial_days' => 0,
                'download_limit_daily' => null,
                'credits_monthly' => 0, 'team_seats' => 25,
                'premium_access' => true, 'source_file_access' => true,
                'editor_access' => true, 'api_access' => true,
                'is_team_plan' => true,
                'is_active' => true, 'is_public' => true, 'sort_order' => 3,
                'features' => [
                    ['Everything in Team', true, true],
                    'Unlimited seats',
                    'Extended and bespoke licensing',
                    'Priority support',
                    'Invoiced billing',
                ],
            ],
        ];
    }
}
