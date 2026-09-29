<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds admin-controllable defaults.
 *
 * Brand colours are the values extracted from the live iconors.com —
 * see docs/BRAND.md for the evidence trail. They are defaults only;
 * an administrator can change any of them without a code change.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->settings() as $row) {
            Setting::updateOrCreate(
                ['key' => $row['key']],
                [
                    'group'        => $row['group'],
                    'type'         => $row['type'],
                    'is_encrypted' => $row['is_encrypted'] ?? false,
                    'is_public'    => $row['is_public'] ?? false,
                    'value'        => $row['value'],
                ]
            );
        }
    }

    private function settings(): array
    {
        return array_merge(
            $this->general(),
            $this->branding(),
            $this->marketplace(),
            $this->seo(),
            $this->royalties(),
            $this->payment(),
            $this->mail(),
        );
    }

    /**
     * SEO and AEO — brief §29.
     *
     * Tracking scripts are stored but only ever emitted when their
     * `_enabled` flag is true: the brief is explicit that a disabled
     * tracker must not load at all, not merely be inert.
     */
    private function seo(): array
    {
        return [
            ['group' => 'seo', 'key' => 'seo_title_format',      'type' => 'string', 'value' => '{page} — {site}', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_default_title',     'type' => 'string', 'value' => 'Iconors — All Your Creative Assets in One Place', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_meta_description',  'type' => 'text',   'value' => 'Download high-quality icons, animations, illustrations and stickers — free and premium.', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_meta_keywords',     'type' => 'text',   'value' => null],
            ['group' => 'seo', 'key' => 'seo_canonical_host',    'type' => 'string', 'value' => null],

            // Master switch. Staging sites get de-indexed by flipping this
            // one flag rather than editing a file nobody remembers exists.
            ['group' => 'seo', 'key' => 'seo_allow_indexing',    'type' => 'boolean', 'value' => true],
            ['group' => 'seo', 'key' => 'seo_robots_extra',      'type' => 'text',    'value' => null],

            ['group' => 'seo', 'key' => 'seo_og_title',          'type' => 'string', 'value' => null, 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_og_description',    'type' => 'text',   'value' => null, 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_twitter_card',      'type' => 'select', 'value' => 'summary_large_image', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_twitter_site',      'type' => 'string', 'value' => null, 'is_public' => true],

            ['group' => 'seo', 'key' => 'seo_organisation_name',  'type' => 'string', 'value' => 'Iconors', 'is_public' => true],
            ['group' => 'seo', 'key' => 'seo_organisation_logo',  'type' => 'string', 'value' => null],
            ['group' => 'seo', 'key' => 'seo_social_profiles',    'type' => 'text',   'value' => null],

            ['group' => 'seo', 'key' => 'seo_sitemap_enabled',    'type' => 'boolean', 'value' => true],
            ['group' => 'seo', 'key' => 'seo_sitemap_assets',     'type' => 'boolean', 'value' => true],
            ['group' => 'seo', 'key' => 'seo_sitemap_categories', 'type' => 'boolean', 'value' => true],
            ['group' => 'seo', 'key' => 'seo_sitemap_contributors','type'=> 'boolean', 'value' => true],

            // Search-console ownership tokens.
            ['group' => 'seo', 'key' => 'seo_google_verification', 'type' => 'string', 'value' => null],
            ['group' => 'seo', 'key' => 'seo_bing_verification',   'type' => 'string', 'value' => null],

            // Analytics — id + explicit enable flag per provider.
            ['group' => 'seo', 'key' => 'analytics_ga_enabled',      'type' => 'boolean', 'value' => false],
            ['group' => 'seo', 'key' => 'analytics_ga_id',           'type' => 'string',  'value' => null],
            ['group' => 'seo', 'key' => 'analytics_gtm_enabled',     'type' => 'boolean', 'value' => false],
            ['group' => 'seo', 'key' => 'analytics_gtm_id',          'type' => 'string',  'value' => null],
            ['group' => 'seo', 'key' => 'analytics_meta_enabled',    'type' => 'boolean', 'value' => false],
            ['group' => 'seo', 'key' => 'analytics_meta_pixel_id',   'type' => 'string',  'value' => null],
            ['group' => 'seo', 'key' => 'analytics_clarity_enabled', 'type' => 'boolean', 'value' => false],
            ['group' => 'seo', 'key' => 'analytics_clarity_id',      'type' => 'string',  'value' => null],
            ['group' => 'seo', 'key' => 'analytics_plausible_enabled','type'=> 'boolean', 'value' => false],
            ['group' => 'seo', 'key' => 'analytics_plausible_domain','type' => 'string',  'value' => null],
        ];
    }

    /**
     * Royalty, payout and refund rules — brief §23.
     *
     * Rates are stored as percentages (0–100) rather than fractions so the
     * admin form shows the same number the business talks about. The
     * conversion to a multiplier happens once, in the royalty calculator.
     */
    private function royalties(): array
    {
        return [
            ['group' => 'royalty', 'key' => 'commission_rate_one_time',     'type' => 'float',   'value' => 50],
            ['group' => 'royalty', 'key' => 'commission_rate_subscription', 'type' => 'float',   'value' => 30],
            ['group' => 'royalty', 'key' => 'commission_rate_api',          'type' => 'float',   'value' => 25],

            // What one subscription download is worth before the contributor's
            // share is taken. Stands in for a full revenue-share pool until
            // subscriptions are actually billing.
            ['group' => 'royalty', 'key' => 'subscription_value_per_download', 'type' => 'float', 'value' => 0.10],

            ['group' => 'royalty', 'key' => 'payout_minimum',        'type' => 'float',   'value' => 50],
            // Holding period exists so a refund can be clawed back from the
            // contributor's pending balance before the money leaves.
            ['group' => 'royalty', 'key' => 'payout_holding_days',   'type' => 'integer', 'value' => 30],
            ['group' => 'royalty', 'key' => 'payout_schedule',       'type' => 'select',  'value' => 'monthly'],
            ['group' => 'royalty', 'key' => 'payout_deduct_refunds', 'type' => 'boolean', 'value' => true],
            ['group' => 'royalty', 'key' => 'payout_fee_fixed',      'type' => 'float',   'value' => 0],
            ['group' => 'royalty', 'key' => 'payout_fee_percent',    'type' => 'float',   'value' => 0],

            ['group' => 'royalty', 'key' => 'refund_window_days',    'type' => 'integer', 'value' => 14, 'is_public' => true],
            ['group' => 'royalty', 'key' => 'refund_policy_summary', 'type' => 'text',    'value' => null, 'is_public' => true],
        ];
    }

    private function general(): array
    {
        return [
            ['group' => 'general', 'key' => 'site_name',      'type' => 'string',  'value' => 'Iconors',  'is_public' => true],
            ['group' => 'general', 'key' => 'site_tagline',   'type' => 'string',  'value' => 'All Your Creative Assets in One Place', 'is_public' => true],
            ['group' => 'general', 'key' => 'site_description', 'type' => 'text',  'value' => 'Download high-quality icons, animations and stickers — free and premium.', 'is_public' => true],
            ['group' => 'general', 'key' => 'admin_email',    'type' => 'string',  'value' => 'admin@iconors.com'],
            ['group' => 'general', 'key' => 'support_email',  'type' => 'string',  'value' => 'support@iconors.com', 'is_public' => true],
            ['group' => 'general', 'key' => 'default_currency', 'type' => 'string', 'value' => 'USD', 'is_public' => true],
            ['group' => 'general', 'key' => 'default_locale', 'type' => 'string',  'value' => 'en',   'is_public' => true],
            ['group' => 'general', 'key' => 'timezone',       'type' => 'string',  'value' => 'UTC'],
            ['group' => 'general', 'key' => 'registration_enabled',  'type' => 'boolean', 'value' => true,  'is_public' => true],
            ['group' => 'general', 'key' => 'contributor_applications_open', 'type' => 'boolean', 'value' => true, 'is_public' => true],
            ['group' => 'general', 'key' => 'maintenance_mode', 'type' => 'boolean', 'value' => false],
        ];
    }

    /**
     * Extracted from iconors.com on 2026-07-18.
     *   #3CF7A2 — <meta name="theme-color"> AND --primary-color (independent confirmation)
     *   #040408 — --primary-text-color
     *   #28375A — WP preset "dark-blue"
     *   #5A01F0 — WP preset "purple"
     *   #1E0046 → #1E0064 — live signature gradient
     */
    private function branding(): array
    {
        return [
            ['group' => 'branding', 'key' => 'brand_primary',        'type' => 'color', 'value' => '#3CF7A2', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_primary_light',  'type' => 'color', 'value' => '#2DFFB3', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_primary_dark',   'type' => 'color', 'value' => '#27D385', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_secondary',      'type' => 'color', 'value' => '#28375A', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_accent',         'type' => 'color', 'value' => '#5A01F0', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_accent_deep',    'type' => 'color', 'value' => '#1E0046', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_heading_color',  'type' => 'color', 'value' => '#040408', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_text_color',     'type' => 'color', 'value' => '#28375A', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_muted_color',    'type' => 'color', 'value' => '#717179', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_link_color',     'type' => 'color', 'value' => '#5A01F0', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_border_color',   'type' => 'color', 'value' => '#E9EDF7', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_surface_color',  'type' => 'color', 'value' => '#FFFFFF', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_background_color','type'=> 'color', 'value' => '#FAF9FB', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_success_color',  'type' => 'color', 'value' => '#27AB81', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_warning_color',  'type' => 'color', 'value' => '#ECCA2E', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_error_color',    'type' => 'color', 'value' => '#E6174B', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_info_color',     'type' => 'color', 'value' => '#5A01F0', 'is_public' => true],

            ['group' => 'branding', 'key' => 'brand_gradient',       'type' => 'string', 'value' => 'linear-gradient(-135deg, #1E0046, #1E0064)', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_radius',         'type' => 'string', 'value' => '14px', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_container_width','type' => 'string', 'value' => '1280px', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_font_body',      'type' => 'string', 'value' => 'Inter', 'is_public' => true],
            ['group' => 'branding', 'key' => 'brand_font_heading',   'type' => 'string', 'value' => 'Inter', 'is_public' => true],

            // Logo variants. The mark is a white knockout, so the light/dark
            // pairing is inverted from the naive expectation — see docs/BRAND.md.
            ['group' => 'branding', 'key' => 'logo_main',    'type' => 'file', 'value' => null, 'is_public' => true],
            ['group' => 'branding', 'key' => 'logo_light',   'type' => 'file', 'value' => null, 'is_public' => true],
            ['group' => 'branding', 'key' => 'logo_dark',    'type' => 'file', 'value' => null, 'is_public' => true],
            ['group' => 'branding', 'key' => 'logo_mobile',  'type' => 'file', 'value' => null, 'is_public' => true],
            ['group' => 'branding', 'key' => 'logo_email',   'type' => 'file', 'value' => null],
            ['group' => 'branding', 'key' => 'logo_invoice', 'type' => 'file', 'value' => null],
            ['group' => 'branding', 'key' => 'favicon',      'type' => 'file', 'value' => null, 'is_public' => true],
            ['group' => 'branding', 'key' => 'social_share_image', 'type' => 'file', 'value' => null, 'is_public' => true],
        ];
    }

    private function marketplace(): array
    {
        return [
            // 0 = unlimited. The entitlement service treats <= 0 as no cap.
            ['group' => 'marketplace', 'key' => 'free_download_limit_daily', 'type' => 'integer', 'value' => 10, 'is_public' => true],
            ['group' => 'marketplace', 'key' => 'guest_download_enabled',    'type' => 'boolean', 'value' => false],
            ['group' => 'marketplace', 'key' => 'upload_max_size_mb',        'type' => 'integer', 'value' => 100],
            ['group' => 'marketplace', 'key' => 'require_asset_review',      'type' => 'boolean', 'value' => true],
            // Short by design: a long-lived signed URL is a shareable
            // bearer token for a premium file.
            ['group' => 'marketplace', 'key' => 'download_url_ttl_minutes',  'type' => 'integer', 'value' => 5],
            ['group' => 'marketplace', 'key' => 'watermark_previews',        'type' => 'boolean', 'value' => true],

            // Homepage section visibility — brief §7 requires each section to
            // be switchable. Contributors are off by default: with a small
            // roster the row looks sparse rather than social-proofing.
            ['group' => 'marketplace', 'key' => 'home_show_contributors', 'type' => 'boolean', 'value' => false, 'is_public' => true],
            ['group' => 'marketplace', 'key' => 'home_show_categories',   'type' => 'boolean', 'value' => true,  'is_public' => true],
            ['group' => 'marketplace', 'key' => 'home_show_types',        'type' => 'boolean', 'value' => true,  'is_public' => true],
        ];
    }

    /**
     * Credentials are is_encrypted and never is_public. They are seeded empty —
     * secrets belong in the admin UI or .env, never in a seeder committed to git.
     */
    private function payment(): array
    {
        return [
            ['group' => 'payment', 'key' => 'stripe_enabled',        'type' => 'boolean', 'value' => false],
            ['group' => 'payment', 'key' => 'stripe_mode',           'type' => 'select',  'value' => 'test'],
            ['group' => 'payment', 'key' => 'stripe_key',            'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'stripe_secret',         'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'stripe_webhook_secret', 'type' => 'string',  'value' => null, 'is_encrypted' => true],

            ['group' => 'payment', 'key' => 'paypal_enabled',        'type' => 'boolean', 'value' => false],
            ['group' => 'payment', 'key' => 'paypal_mode',           'type' => 'select',  'value' => 'sandbox'],
            ['group' => 'payment', 'key' => 'paypal_client_id',      'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'paypal_secret',         'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'paypal_webhook_id',     'type' => 'string',  'value' => null, 'is_encrypted' => true],

            ['group' => 'payment', 'key' => 'payoneer_enabled',         'type' => 'boolean', 'value' => false],
            ['group' => 'payment', 'key' => 'payoneer_mode',            'type' => 'select',  'value' => 'sandbox'],
            ['group' => 'payment', 'key' => 'payoneer_program_id',      'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'payoneer_api_username',    'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'payoneer_api_password',    'type' => 'string',  'value' => null, 'is_encrypted' => true],
            ['group' => 'payment', 'key' => 'payoneer_webhook_secret',  'type' => 'string',  'value' => null, 'is_encrypted' => true],

            ['group' => 'payment', 'key' => 'bank_transfer_enabled', 'type' => 'boolean', 'value' => false],
            ['group' => 'payment', 'key' => 'bank_transfer_instructions', 'type' => 'text', 'value' => null],
        ];
    }

    private function mail(): array
    {
        return [
            ['group' => 'mail', 'key' => 'mail_driver',     'type' => 'select', 'value' => 'log'],
            ['group' => 'mail', 'key' => 'mail_host',       'type' => 'string', 'value' => null],
            ['group' => 'mail', 'key' => 'mail_port',       'type' => 'integer','value' => 587],
            ['group' => 'mail', 'key' => 'mail_encryption', 'type' => 'select', 'value' => 'tls'],
            ['group' => 'mail', 'key' => 'mail_username',   'type' => 'string', 'value' => null, 'is_encrypted' => true],
            ['group' => 'mail', 'key' => 'mail_password',   'type' => 'string', 'value' => null, 'is_encrypted' => true],
            ['group' => 'mail', 'key' => 'mail_from_name',  'type' => 'string', 'value' => 'Iconors'],
            ['group' => 'mail', 'key' => 'mail_from_email', 'type' => 'string', 'value' => 'noreply@iconors.com'],
            ['group' => 'mail', 'key' => 'mail_reply_to',   'type' => 'string', 'value' => 'support@iconors.com'],
        ];
    }
}
