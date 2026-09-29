<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Asset\SvgSanitizer;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Admin settings screens — brief §21, §27, §30.
 *
 * Secrets are write-only from the UI's point of view: they are never
 * rendered back into a form field, only ever shown as a masked hint.
 * A populated password input is a credential sitting in the page source,
 * readable by any browser extension or shoulder-surfer, and it leaks
 * into browser autofill and HTML caches.
 */
class SettingsController extends Controller
{
    /** Keys that hold secrets. Masked on read, only written when non-empty. */
    private const SECRET_KEYS = [
        'stripe_key', 'stripe_secret', 'stripe_webhook_secret',
        'paypal_client_id', 'paypal_secret', 'paypal_webhook_id',
        'payoneer_program_id', 'payoneer_api_username',
        'payoneer_api_password', 'payoneer_webhook_secret',
        'mail_username', 'mail_password',
    ];

    /** Site identity, contact addresses, locale, registration — brief §30. */
    public function general(Request $request)
    {
        $this->gate($request, 'settings.manage');

        return view('admin.settings.general', ['settings' => $this->presented('general')]);
    }

    public function updateGeneral(Request $request)
    {
        $this->gate($request, 'settings.manage');

        $validated = $request->validate([
            'site_name'        => ['required', 'string', 'max:80'],
            'site_tagline'     => ['required', 'string', 'max:160'],
            'site_description' => ['required', 'string', 'max:300'],
            'admin_email'      => ['required', 'email', 'max:255'],
            'support_email'    => ['required', 'email', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3'],
            'default_locale'   => ['required', 'string', 'max:10'],
            'timezone'         => ['required', 'timezone'],
            'registration_enabled'           => ['nullable', 'boolean'],
            'contributor_applications_open'  => ['nullable', 'boolean'],
            'maintenance_mode'               => ['nullable', 'boolean'],
        ], [
            'site_description.max' => 'Keep this under 300 characters — it is used as the default meta description.',
        ]);

        $validated['default_currency'] = strtoupper($validated['default_currency']);

        $warning = null;

        if ($request->boolean('maintenance_mode')) {
            $warning = 'Maintenance mode is ON. The public site is now hidden from visitors. '
                .'You will still be able to reach the admin area.';
        }

        $this->persist($validated, $request);

        return back()->with('status', 'General settings saved.')->with('warning', $warning);
    }

    /**
     * Branding — brief §2.
     *
     * This is the screen that makes "change a colour, restyle the whole
     * site" true. Values land in `settings`, SettingsService emits them as
     * :root custom properties, and every component reads var(--ico-*), so
     * nothing here needs a rebuild or a deploy.
     */
    public function branding(Request $request)
    {
        $this->gate($request, 'branding.manage');

        return view('admin.settings.branding', ['settings' => $this->presented('branding')]);
    }

    public function updateBranding(Request $request)
    {
        $this->gate($request, 'branding.manage');

        $colorRule = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $validated = $request->validate([
            'brand_primary'          => $colorRule,
            'brand_primary_light'    => $colorRule,
            'brand_primary_dark'     => $colorRule,
            'brand_secondary'        => $colorRule,
            'brand_accent'           => $colorRule,
            'brand_accent_deep'      => $colorRule,
            'brand_heading_color'    => $colorRule,
            'brand_text_color'       => $colorRule,
            'brand_muted_color'      => $colorRule,
            'brand_link_color'       => $colorRule,
            'brand_border_color'     => $colorRule,
            'brand_surface_color'    => $colorRule,
            'brand_background_color' => $colorRule,
            'brand_success_color'    => $colorRule,
            'brand_warning_color'    => $colorRule,
            'brand_error_color'      => $colorRule,
            'brand_info_color'       => $colorRule,

            'brand_gradient'        => ['nullable', 'string', 'max:255'],
            'brand_radius'          => ['nullable', 'string', 'regex:/^\d{1,3}(px|rem)$/'],
            'brand_container_width' => ['nullable', 'string', 'regex:/^\d{3,4}px$/'],
            'brand_font_body'       => ['nullable', 'string', 'max:120', "regex:/^[A-Za-z0-9 ,'\-]+$/"],
            'brand_font_heading'    => ['nullable', 'string', 'max:120', "regex:/^[A-Za-z0-9 ,'\-]+$/"],
        ], [
            'brand_primary.regex'         => 'Colours must be a hex value such as #3CF7A2.',
            'brand_radius.regex'          => 'Use a value like 14px or 1rem.',
            'brand_container_width.regex' => 'Use a pixel value such as 1280px.',
            'brand_font_body.regex'       => 'Font names may only contain letters, numbers, spaces, commas and hyphens.',
        ]);

        $uploaded = $this->storeLogos($request);

        // Contrast advisory. The mint primary is a FILL that carries
        // near-black text; picked as a text colour it fails AA outright.
        // See docs/BRAND.md.
        $warning = null;
        $primary = $validated['brand_primary'] ?? $this->settingValue('brand_primary');
        $surface = $validated['brand_surface_color'] ?? $this->settingValue('brand_surface_color');

        if ($primary && $surface) {
            $ratio = $this->contrastRatio($primary, $surface);

            if ($ratio < 3) {
                $warning = 'Heads up: your primary colour has only '.number_format($ratio, 1).':1 contrast '
                    .'against the surface colour. It works as a button fill (we pick a readable label '
                    .'automatically) but will be unreadable if used as text.';
            }
        }

        $this->persist(array_merge($validated, $uploaded), $request);

        return back()
            ->with('status', 'Branding saved — the whole site updated instantly.')
            ->with('warning', $warning);
    }

    /**
     * Handles logo/favicon uploads.
     *
     * Logos are very often SVG, and an SVG is an executable document. These
     * are rendered on every page including the admin, so they go through the
     * same sanitiser as contributor uploads — an unsanitised logo would be
     * stored XSS on every single page of the site.
     */
    private function storeLogos(Request $request): array
    {
        $fields = [
            'logo_main', 'logo_light', 'logo_dark', 'logo_mobile',
            'logo_email', 'logo_invoice', 'favicon', 'social_share_image',
        ];

        $request->validate(
            array_fill_keys(
                array_map(fn ($f) => "{$f}", $fields),
                ['nullable', 'file', 'mimes:svg,png,jpg,jpeg,webp,ico', 'max:2048'],
            ),
            ['*.mimes' => 'Use an SVG, PNG, JPG, WebP or ICO file.',
             '*.max'   => 'Images must be under 2 MB.'],
        );

        $stored = [];

        foreach ($fields as $field) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $file      = $request->file($field);
            $extension = strtolower($file->getClientOriginalExtension());
            $contents  = file_get_contents($file->getRealPath());

            if ($contents === false) {
                continue;
            }

            if ($extension === 'svg') {
                $result = app(SvgSanitizer::class)->sanitize($contents);

                if ($result['svg'] === '') {
                    // Rejected rather than stored raw — a logo that cannot be
                    // made safe does not get to sit on every page.
                    continue;
                }

                $contents = $result['svg'];
            }

            // Content-hashed name busts any CDN/browser cache on replacement.
            $path = "branding/{$field}-".substr(sha1($contents), 0, 10).".{$extension}";
            Storage::disk('public')->put($path, $contents);

            $stored[$field] = $path;
        }

        return $stored;
    }

    private function settingValue(string $key): ?string
    {
        $value = Setting::where('key', $key)->first()?->value;

        return is_string($value) ? $value : null;
    }

    /** WCAG contrast ratio between two hex colours. */
    private function contrastRatio(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $hex = ltrim($hex, '#');

            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            $c = [];
            foreach ([0, 2, 4] as $i) {
                $v = hexdec(substr($hex, $i, 2)) / 255;
                $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };

        $l1 = $lum($a);
        $l2 = $lum($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /** SEO, structured data, sitemaps and analytics — brief §28, §29. */
    public function seo(Request $request)
    {
        $this->gate($request, 'seo.manage');

        return view('admin.settings.seo', ['settings' => $this->presented('seo')]);
    }

    public function updateSeo(Request $request)
    {
        $this->gate($request, 'seo.manage');

        $validated = $request->validate([
            'seo_title_format'     => ['required', 'string', 'max:120'],
            'seo_default_title'    => ['required', 'string', 'max:180'],
            'seo_meta_description' => ['required', 'string', 'max:320'],
            'seo_meta_keywords'    => ['nullable', 'string', 'max:500'],
            'seo_canonical_host'   => ['nullable', 'url', 'max:255'],

            'seo_allow_indexing'   => ['nullable', 'boolean'],
            'seo_robots_extra'     => ['nullable', 'string', 'max:2000'],

            'seo_og_title'        => ['nullable', 'string', 'max:180'],
            'seo_og_description'  => ['nullable', 'string', 'max:320'],
            'seo_twitter_card'    => ['required', 'in:summary,summary_large_image'],
            'seo_twitter_site'    => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]{1,15}$/'],

            'seo_organisation_name' => ['required', 'string', 'max:120'],
            'seo_social_profiles'   => ['nullable', 'string', 'max:2000'],

            'seo_sitemap_enabled'      => ['nullable', 'boolean'],
            'seo_sitemap_assets'       => ['nullable', 'boolean'],
            'seo_sitemap_categories'   => ['nullable', 'boolean'],
            'seo_sitemap_contributors' => ['nullable', 'boolean'],

            // Verification tokens are opaque strings that land in a meta
            // tag, so they are constrained to a safe character set.
            'seo_google_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'seo_bing_verification'   => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],

            // Analytics ids are interpolated into inline <script> blocks.
            // Each is pinned to its provider's real format — a free-text id
            // here would be script injection on every page of the site.
            'analytics_ga_enabled'       => ['nullable', 'boolean'],
            'analytics_ga_id'            => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{6,12}$/'],
            'analytics_gtm_enabled'      => ['nullable', 'boolean'],
            'analytics_gtm_id'           => ['nullable', 'string', 'regex:/^GTM-[A-Z0-9]{4,10}$/'],
            'analytics_meta_enabled'     => ['nullable', 'boolean'],
            'analytics_meta_pixel_id'    => ['nullable', 'string', 'regex:/^\d{8,20}$/'],
            'analytics_clarity_enabled'  => ['nullable', 'boolean'],
            'analytics_clarity_id'       => ['nullable', 'string', 'regex:/^[a-z0-9]{6,15}$/'],
            'analytics_plausible_enabled'=> ['nullable', 'boolean'],
            'analytics_plausible_domain' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.\-]+$/i'],
        ], [
            'analytics_ga_id.regex'         => 'A Google Analytics 4 ID looks like G-ABC123DEF.',
            'analytics_gtm_id.regex'        => 'A Tag Manager ID looks like GTM-ABC123.',
            'analytics_meta_pixel_id.regex' => 'A Meta Pixel ID is numeric.',
            'seo_twitter_site.regex'        => 'Use an X/Twitter handle such as @iconors.',
        ]);

        $warning = null;

        if (! $request->boolean('seo_allow_indexing')) {
            $warning = 'Search indexing is OFF. robots.txt now disallows everything and every page '
                .'sends noindex. Search engines will drop this site — only leave this off on staging.';
        }

        foreach (['ga', 'gtm', 'meta', 'clarity', 'plausible'] as $provider) {
            $idKey = match ($provider) {
                'meta'      => 'analytics_meta_pixel_id',
                'plausible' => 'analytics_plausible_domain',
                default     => "analytics_{$provider}_id",
            };

            if ($request->boolean("analytics_{$provider}_enabled") && blank($validated[$idKey] ?? null)
                && blank($this->settingValue($idKey))) {
                return back()->withInput()->withErrors([
                    "analytics_{$provider}_enabled" => 'Add the ID before enabling this tracker.',
                ]);
            }
        }

        $this->persist($validated, $request);

        return back()->with('status', 'SEO settings saved.')->with('warning', $warning);
    }

    public function payments(Request $request)
    {
        $this->gate($request, 'payments.manage');

        return view('admin.settings.payments', [
            'settings' => $this->presented('payment'),
        ]);
    }

    public function updatePayments(Request $request, SettingsService $service)
    {
        $this->gate($request, 'payments.manage');

        $validated = $request->validate([
            'stripe_enabled'        => ['nullable', 'boolean'],
            'stripe_mode'           => ['required', 'in:test,live'],
            'stripe_key'            => ['nullable', 'string', 'max:255'],
            'stripe_secret'         => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],

            'paypal_enabled'        => ['nullable', 'boolean'],
            'paypal_mode'           => ['required', 'in:sandbox,live'],
            'paypal_client_id'      => ['nullable', 'string', 'max:255'],
            'paypal_secret'         => ['nullable', 'string', 'max:255'],
            'paypal_webhook_id'     => ['nullable', 'string', 'max:255'],

            'payoneer_enabled'        => ['nullable', 'boolean'],
            'payoneer_mode'           => ['required', 'in:sandbox,live'],
            'payoneer_program_id'     => ['nullable', 'string', 'max:255'],
            'payoneer_api_username'   => ['nullable', 'string', 'max:255'],
            'payoneer_api_password'   => ['nullable', 'string', 'max:255'],
            'payoneer_webhook_secret' => ['nullable', 'string', 'max:255'],

            'bank_transfer_enabled'      => ['nullable', 'boolean'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        // Guard against going live with an empty credential — that fails at
        // the gateway during a real customer checkout, which is the worst
        // possible moment to discover it.
        foreach ([
            ['stripe', 'stripe_secret'],
            ['paypal', 'paypal_secret'],
            ['payoneer', 'payoneer_api_password'],
        ] as [$gateway, $secretKey]) {
            $enabling = $request->boolean("{$gateway}_enabled");
            $hasStored = filled($service->get($secretKey));
            $hasNew    = filled($validated[$secretKey] ?? null);

            if ($enabling && ! $hasStored && ! $hasNew) {
                return back()->withErrors([
                    "{$gateway}_enabled" => ucfirst($gateway).' cannot be enabled until its credentials are saved.',
                ])->withInput();
            }
        }

        $this->persist($validated, $request);

        return back()->with('status', 'Payment settings saved.');
    }

    /**
     * Royalty, payout and refund rules — brief §23.
     *
     * Changing a rate here affects FUTURE earnings only. Existing
     * order_items froze their commission_rate at purchase time, so a rate
     * change can never retroactively alter what a contributor already earned.
     */
    public function royalties(Request $request)
    {
        $this->gate($request, 'payouts.manage');

        return view('admin.settings.royalties', [
            'settings' => $this->presented('royalty'),
        ]);
    }

    public function updateRoyalties(Request $request)
    {
        $this->gate($request, 'payouts.manage');

        $validated = $request->validate([
            'commission_rate_one_time'     => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_subscription' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_api'          => ['required', 'numeric', 'min:0', 'max:100'],

            'payout_minimum'        => ['required', 'numeric', 'min:0', 'max:100000'],
            'payout_holding_days'   => ['required', 'integer', 'min:0', 'max:365'],
            'payout_schedule'       => ['required', 'in:manual,weekly,fortnightly,monthly'],
            'payout_deduct_refunds' => ['nullable', 'boolean'],
            'payout_fee_fixed'      => ['required', 'numeric', 'min:0', 'max:1000'],
            'payout_fee_percent'    => ['required', 'numeric', 'min:0', 'max:100'],

            'refund_window_days'    => ['required', 'integer', 'min:0', 'max:365'],
            'refund_policy_summary' => ['nullable', 'string', 'max:5000'],
        ], [
            'commission_rate_one_time.max' => 'A commission rate above 100% would pay out more than the sale earned.',
        ]);

        // A holding period shorter than the refund window means money can
        // leave before a refund is possible — the platform then eats the
        // clawback. Warn rather than block: some operators accept that risk.
        $warning = null;
        if ((int) $validated['payout_holding_days'] < (int) $validated['refund_window_days']) {
            $warning = 'Note: your payout holding period ('.$validated['payout_holding_days']
                .' days) is shorter than your refund window ('.$validated['refund_window_days']
                .' days). Contributors may be paid before a refund can be requested, '
                .'leaving the platform to absorb the clawback.';
        }

        $this->persist($validated, $request);

        return back()
            ->with('status', 'Royalty and payout settings saved.')
            ->with('warning', $warning);
    }

    /** Marketplace rules — download limits, upload caps, review policy. */
    public function marketplace(Request $request)
    {
        $this->gate($request, 'settings.manage');

        return view('admin.settings.marketplace', [
            'settings' => $this->presented('marketplace'),
        ]);
    }

    public function updateMarketplace(Request $request)
    {
        $this->gate($request, 'settings.manage');

        $validated = $request->validate([
            // 0 = unlimited, handled explicitly by the entitlement service.
            'free_download_limit_daily' => ['required', 'integer', 'min:0', 'max:100000'],
            'guest_download_enabled'    => ['nullable', 'boolean'],
            'upload_max_size_mb'        => ['required', 'integer', 'min:1', 'max:2048'],
            'require_asset_review'      => ['nullable', 'boolean'],
            'download_url_ttl_minutes'  => ['required', 'integer', 'min:1', 'max:1440'],
            'watermark_previews'        => ['nullable', 'boolean'],
        ], [
            'download_url_ttl_minutes.max' => 'A signed link valid for more than a day is effectively permanent.',
        ]);

        $warning = null;

        if (! $request->boolean('require_asset_review')) {
            $warning = 'Asset review is now OFF. Contributor uploads will publish immediately '
                .'without moderation, including any copyright-infringing or unsuitable material.';
        }

        if ((int) $validated['download_url_ttl_minutes'] > 60) {
            $warning = trim(($warning ? $warning.' ' : '')
                .'A download link lifetime above 60 minutes gives a shared link a long window of use.');
        }

        $this->persist($validated, $request);

        return back()
            ->with('status', 'Marketplace settings saved.')
            ->with('warning', $warning);
    }

    public function mail(Request $request)
    {
        $this->gate($request, 'smtp.manage');

        return view('admin.settings.mail', [
            'settings' => $this->presented('mail'),
        ]);
    }

    public function updateMail(Request $request)
    {
        $this->gate($request, 'smtp.manage');

        $validated = $request->validate([
            'mail_driver'     => ['required', 'in:log,smtp,ses,sendgrid,mailgun,postmark,resend,brevo'],
            'mail_host'       => ['nullable', 'string', 'max:255'],
            'mail_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_username'   => ['nullable', 'string', 'max:255'],
            'mail_password'   => ['nullable', 'string', 'max:255'],
            'mail_from_name'  => ['required', 'string', 'max:120'],
            'mail_from_email' => ['required', 'email', 'max:255'],
            'mail_reply_to'   => ['nullable', 'email', 'max:255'],
        ]);

        $this->persist($validated, $request);

        return back()->with('status', 'Email settings saved.');
    }

    /**
     * Sends a test message using the SAVED settings.
     *
     * Brief §27 requires a test button. Failures are surfaced verbatim
     * because "connection refused" and "authentication failed" need
     * completely different fixes, and a generic message hides which.
     */
    public function testMail(Request $request, SettingsService $service)
    {
        $this->gate($request, 'smtp.manage');

        $validated = $request->validate([
            'test_recipient' => ['required', 'email'],
        ]);

        try {
            $this->applyMailConfig($service);

            Mail::raw(
                "This is a test message from Iconors.\n\n"
                ."If you received it, your email settings are working.\n"
                ."Sent at ".now()->toDateTimeString().' UTC.',
                fn ($message) => $message
                    ->to($validated['test_recipient'])
                    ->subject('Iconors — SMTP test'),
            );

            $this->logEmail($validated['test_recipient'], 'Iconors — SMTP test', 'sent', $service);

            return back()->with('status', "Test email sent to {$validated['test_recipient']}.");
        } catch (\Throwable $e) {
            $this->logEmail($validated['test_recipient'], 'Iconors — SMTP test', 'failed', $service, $e->getMessage());

            return back()->withErrors([
                'test_recipient' => 'Send failed: '.$e->getMessage(),
            ]);
        }
    }

    /** Applies DB-stored mail settings to the live config for this request. */
    private function applyMailConfig(SettingsService $service): void
    {
        $driver = $service->get('mail_driver', 'log');

        config([
            'mail.default'                       => $driver,
            'mail.mailers.smtp.host'             => $service->get('mail_host'),
            'mail.mailers.smtp.port'             => (int) $service->get('mail_port', 587),
            'mail.mailers.smtp.username'         => $service->get('mail_username'),
            'mail.mailers.smtp.password'         => $service->get('mail_password'),
            'mail.mailers.smtp.encryption'       => $service->get('mail_encryption') === 'none'
                ? null
                : $service->get('mail_encryption', 'tls'),
            'mail.from.address'                  => $service->get('mail_from_email', 'noreply@iconors.com'),
            'mail.from.name'                     => $service->get('mail_from_name', 'Iconors'),
        ]);
    }

    private function logEmail(string $to, string $subject, string $status, SettingsService $service, ?string $error = null): void
    {
        DB::table('email_logs')->insert([
            'recipient'     => $to,
            'subject'       => $subject,
            'template_key'  => 'smtp_test',
            'provider'      => $service->get('mail_driver', 'log'),
            'status'        => $status,
            'error_message' => $error,
            'sent_at'       => $status === 'sent' ? now() : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Writes settings, skipping blank secrets so an untouched masked field
     * does not wipe a stored credential.
     */
    /**
     * Writes settings.
     *
     * Two subtleties, both of which cause silent data loss if missed:
     *
     * 1. A blank SECRET means "keep the stored value", not "clear it".
     *    The form only ever shows a mask, so an untouched field submits
     *    empty — treating that as a clear would wipe a live credential.
     *
     * 2. An unticked checkbox is ABSENT from the request, so Laravel's
     *    validator drops it from $validated entirely. Booleans are
     *    therefore resolved from the Setting's own type against the raw
     *    request, not from the validated payload.
     */
    private function persist(array $validated, Request $request): void
    {
        $service = app(SettingsService::class);

        // Every boolean setting the submitted form could possibly own.
        // Resolved from the DB so a new toggle needs no code change here.
        $booleanKeys = Setting::whereIn('key', $this->formKeys($request, $validated))
            ->where('type', 'boolean')
            ->pluck('key')
            ->all();

        foreach ($validated as $key => $value) {
            if (in_array($key, self::SECRET_KEYS, true) && blank($value)) {
                continue;
            }

            if (in_array($key, $booleanKeys, true)) {
                continue; // handled below
            }

            if ($setting = Setting::where('key', $key)->first()) {
                $setting->value = $value;
                $setting->save();
            }
        }

        foreach ($booleanKeys as $key) {
            if ($setting = Setting::where('key', $key)->first()) {
                $setting->value = $request->boolean($key);
                $setting->save();
            }
        }

        $service->flush();

        $this->audit($request, array_keys($validated));
    }

    /**
     * Keys the submitted form could have contained: validated keys plus a
     * hidden `_fields` marker listing every checkbox the form rendered.
     * Without the marker an unticked box is indistinguishable from a
     * field the form never had.
     */
    private function formKeys(Request $request, array $validated): array
    {
        $declared = array_filter(explode(',', (string) $request->input('_fields', '')));

        return array_unique(array_merge(array_keys($validated), $declared));
    }

    /**
     * Returns a group's settings with secrets replaced by a masked hint.
     * The real value never reaches the view.
     */
    private function presented(string $group): array
    {
        $out = [];

        foreach (Setting::where('group', $group)->get() as $setting) {
            $value = $setting->value;

            if (in_array($setting->key, self::SECRET_KEYS, true)) {
                $out[$setting->key] = [
                    'configured' => filled($value),
                    'hint'       => filled($value) ? '••••••••'.substr((string) $value, -4) : null,
                    'value'      => null,
                ];

                continue;
            }

            $out[$setting->key] = ['configured' => filled($value), 'hint' => null, 'value' => $value];
        }

        return $out;
    }

    /** Records WHICH keys changed, never their values — brief §33. */
    private function audit(Request $request, array $keys): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $request->user()->id,
            'actor_label' => $request->user()->email,
            'action'      => 'settings.updated',
            'target_type' => Setting::class,
            'new_values'  => json_encode(['keys' => array_values(array_diff($keys, self::SECRET_KEYS))]),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }

    private function gate(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}
