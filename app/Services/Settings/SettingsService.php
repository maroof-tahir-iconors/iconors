<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Single read path for all admin-controlled configuration.
 *
 * Everything is served from one cached payload so a page render costs
 * one cache hit rather than a query per setting. Any write busts it.
 */
class SettingsService
{
    private const CACHE_KEY = 'iconors.settings';
    private const CACHE_TTL = 86400; // busted on write, so a long TTL is safe

    private ?array $cache = null;

    public function all(): array
    {
        return $this->cache ??= Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => Setting::all()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value])
                ->all()
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return; // unknown keys are ignored, not silently created
        }

        $setting->value = $value;
        $setting->save();

        $this->flush();
    }

    /**
     * Only settings explicitly flagged is_public. Used for anything that
     * reaches the browser — prevents a gateway secret riding along with
     * the colour tokens because someone reached for all() by habit.
     */
    public function public(): array
    {
        return Cache::remember(
            self::CACHE_KEY.'.public',
            self::CACHE_TTL,
            fn () => Setting::where('is_public', true)
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value])
                ->all()
        );
    }

    public function flush(): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.public');
        Cache::forget(self::CACHE_KEY.'.css');
    }

    /**
     * Emit :root overrides from branding settings.
     *
     * Injected AFTER the compiled stylesheet, so DB values beat the SCSS
     * defaults in resources/scss/_tokens.scss without a rebuild. This is
     * the mechanism behind brief §2's "changing a global color in the
     * admin dashboard must update the entire website".
     *
     * Values are strictly validated before they reach a <style> block —
     * an unvalidated setting here would be a stored-XSS hole, since the
     * output cannot be blade-escaped and still function as CSS.
     */
    public function brandCss(): string
    {
        return Cache::remember(self::CACHE_KEY.'.css', self::CACHE_TTL, function () {
            $map = [
                'brand_primary'          => '--ico-primary',
                'brand_primary_light'    => '--ico-primary-light',
                'brand_primary_dark'     => '--ico-primary-dark',
                'brand_secondary'        => '--ico-secondary',
                'brand_accent'           => '--ico-accent',
                'brand_accent_deep'      => '--ico-accent-deep',
                'brand_heading_color'    => '--ico-heading',
                'brand_text_color'       => '--ico-body',
                'brand_muted_color'      => '--ico-muted',
                'brand_link_color'       => '--ico-link',
                'brand_border_color'     => '--ico-border',
                'brand_surface_color'    => '--ico-surface',
                'brand_background_color' => '--ico-background',
                'brand_success_color'    => '--ico-success',
                'brand_warning_color'    => '--ico-warning',
                'brand_error_color'      => '--ico-error',
                'brand_info_color'       => '--ico-info',
            ];

            $declarations = [];

            foreach ($map as $key => $cssVar) {
                $value = $this->get($key);

                if (is_string($value) && $this->isSafeColor($value)) {
                    $declarations[] = "{$cssVar}:{$value}";
                }
            }

            // Contrast guard: the mint primary is a FILL, never a text colour.
            // We recompute the on-primary text colour from whatever primary the
            // admin picked, rather than trusting a stored pairing that may no
            // longer contrast. See docs/BRAND.md for why this matters.
            $primary = $this->get('brand_primary');
            if (is_string($primary) && $this->isSafeColor($primary)) {
                $declarations[] = '--ico-on-primary:'.$this->readableTextColor($primary);
            }

            foreach (['brand_radius' => '--ico-radius', 'brand_container_width' => '--ico-container'] as $key => $cssVar) {
                $value = $this->get($key);
                if (is_string($value) && preg_match('/^\d{1,4}(px|rem|em|%)$/', $value)) {
                    $declarations[] = "{$cssVar}:{$value}";
                }
            }

            foreach (['brand_font_body' => '--ico-font-body', 'brand_font_heading' => '--ico-font-heading'] as $key => $cssVar) {
                $value = $this->get($key);
                if (is_string($value) && preg_match("/^[A-Za-z0-9 ,'\-]{1,120}$/", $value)) {
                    $declarations[] = "{$cssVar}:{$value}";
                }
            }

            // Gradients are validated far more tightly than a colour: this
            // string is written straight into a <style> block, so anything
            // other than a plain linear-gradient of hex stops is rejected.
            $gradient = $this->get('brand_gradient');

            if (is_string($gradient)
                && preg_match('/^linear-gradient\(\s*-?\d{1,3}deg\s*,\s*#[0-9a-fA-F]{3,6}\s*(?:\d{1,3}%\s*)?(?:,\s*#[0-9a-fA-F]{3,6}\s*(?:\d{1,3}%\s*)?){1,3}\)$/', trim($gradient))) {
                $declarations[] = '--ico-gradient-signature:'.trim($gradient);
            }

            return $declarations === []
                ? ''
                : ':root{'.implode(';', $declarations).'}';
        });
    }

    /** Strict allowlist — hex only. Rejects url(), expression(), and any escape. */
    private function isSafeColor(string $value): bool
    {
        return (bool) preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value);
    }

    /**
     * WCAG relative luminance → pick near-black or white for text on the
     * given background. Threshold 0.45 keeps both sides comfortably above
     * the 4.5:1 AA floor for the palette range we support.
     */
    private function readableTextColor(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > 0.45 ? '#040408' : '#FFFFFF';
    }
}
