@extends('layouts.admin')

@section('title', 'SEO & analytics')
@section('subtitle', 'Search visibility, structured data, sitemaps and tracking.')

@php
    // One row per analytics provider — each pairs an enable switch with its
    // own ID field, format and placeholder. Kept as data so the markup below
    // stays a single loop instead of five near-identical blocks.
    $analyticsProviders = [
        'ga' => [
            'label'       => 'Google Analytics',
            'id_key'      => 'analytics_ga_id',
            'id_label'    => 'Measurement ID',
            'placeholder' => 'G-ABC123DEF',
        ],
        'gtm' => [
            'label'       => 'Google Tag Manager',
            'id_key'      => 'analytics_gtm_id',
            'id_label'    => 'Container ID',
            'placeholder' => 'GTM-ABC123',
        ],
        'meta' => [
            'label'       => 'Meta Pixel',
            'id_key'      => 'analytics_meta_pixel_id',
            'id_label'    => 'Pixel ID',
            'placeholder' => '123456789012345',
        ],
        'clarity' => [
            'label'       => 'Microsoft Clarity',
            'id_key'      => 'analytics_clarity_id',
            'id_label'    => 'Project ID',
            'placeholder' => 'abcd1234ef',
        ],
        'plausible' => [
            'label'       => 'Plausible Analytics',
            'id_key'      => 'analytics_plausible_domain',
            'id_label'    => 'Domain',
            'placeholder' => 'iconors.com',
        ],
    ];
@endphp

@section('content')

    <form method="POST" action="{{ route('admin.settings.seo.update') }}" novalidate>
        @csrf
        <input type="hidden" name="_fields" value="seo_allow_indexing,seo_sitemap_enabled,seo_sitemap_assets,seo_sitemap_categories,seo_sitemap_contributors,analytics_ga_enabled,analytics_gtm_enabled,analytics_meta_enabled,analytics_clarity_enabled,analytics_plausible_enabled">

        {{-- 1. Search appearance --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Search appearance</h2>

            <div class="mb-3">
                <label for="seo_title_format" class="form-label small fw-semibold">Title format</label>
                <input id="seo_title_format" name="seo_title_format" type="text" maxlength="120"
                       class="form-control @error('seo_title_format') is-invalid @enderror"
                       value="{{ old('seo_title_format', $settings['seo_title_format']['value'] ?? '') }}"
                       aria-describedby="seo_title_format-help">
                <div id="seo_title_format-help" class="form-text small">
                    Use {page} and {site} as placeholders, e.g. {page} — {site}
                </div>
                @error('seo_title_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_default_title" class="form-label small fw-semibold">Default title</label>
                <input id="seo_default_title" name="seo_default_title" type="text" maxlength="180"
                       class="form-control @error('seo_default_title') is-invalid @enderror"
                       value="{{ old('seo_default_title', $settings['seo_default_title']['value'] ?? '') }}">
                @error('seo_default_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_meta_description" class="form-label small fw-semibold">Meta description</label>
                <textarea id="seo_meta_description" name="seo_meta_description" rows="3" maxlength="320"
                          class="form-control @error('seo_meta_description') is-invalid @enderror"
                          aria-describedby="seo_meta_description-help seo_meta_description-counter">{{ old('seo_meta_description', $settings['seo_meta_description']['value'] ?? '') }}</textarea>
                <div class="d-flex justify-content-between flex-wrap gap-1">
                    <div id="seo_meta_description-help" class="form-text small">
                        Google typically shows about 155 characters.
                    </div>
                    <div id="seo_meta_description-counter" class="form-text small" data-char-counter aria-live="polite">
                        {{ strlen((string) old('seo_meta_description', $settings['seo_meta_description']['value'] ?? '')) }} / 320
                    </div>
                </div>
                @error('seo_meta_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_meta_keywords" class="form-label small fw-semibold">Meta keywords</label>
                <textarea id="seo_meta_keywords" name="seo_meta_keywords" rows="2" maxlength="500"
                          class="form-control @error('seo_meta_keywords') is-invalid @enderror"
                          aria-describedby="seo_meta_keywords-help">{{ old('seo_meta_keywords', $settings['seo_meta_keywords']['value'] ?? '') }}</textarea>
                <div id="seo_meta_keywords-help" class="form-text small">
                    Most search engines ignore this. Safe to leave blank.
                </div>
                @error('seo_meta_keywords')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="seo_canonical_host" class="form-label small fw-semibold">Canonical host</label>
                <input id="seo_canonical_host" name="seo_canonical_host" type="url" maxlength="255"
                       class="form-control @error('seo_canonical_host') is-invalid @enderror"
                       value="{{ old('seo_canonical_host', $settings['seo_canonical_host']['value'] ?? '') }}"
                       aria-describedby="seo_canonical_host-help">
                <div id="seo_canonical_host-help" class="form-text small">
                    e.g. https://iconors.com — set this in production so canonical URLs use your real domain.
                </div>
                @error('seo_canonical_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- 2. Indexing --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Indexing</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="seo_allow_indexing" name="seo_allow_indexing" value="1"
                           @checked(old('seo_allow_indexing', $settings['seo_allow_indexing']['value'] ?? true))
                           aria-describedby="seo_allow_indexing-help">
                    <label class="form-check-label small fw-semibold text-warning" for="seo_allow_indexing">
                        Allow search engines to index this site
                    </label>
                </div>
                <div id="seo_allow_indexing-help" class="form-text small">
                    Turn this OFF on staging. When off, robots.txt disallows everything and every page sends noindex.
                </div>
                @error('seo_allow_indexing')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_robots_extra" class="form-label small fw-semibold">Extra robots.txt rules</label>
                <textarea id="seo_robots_extra" name="seo_robots_extra" rows="3" maxlength="2000"
                          class="form-control @error('seo_robots_extra') is-invalid @enderror"
                          aria-describedby="seo_robots_extra-help">{{ old('seo_robots_extra', $settings['seo_robots_extra']['value'] ?? '') }}</textarea>
                <div id="seo_robots_extra-help" class="form-text small">
                    Extra robots.txt lines, appended verbatim.
                </div>
                @error('seo_robots_extra')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="border rounded p-3 small mb-1">
                <p class="fw-semibold mb-2">Preview your live robots.txt / sitemap</p>
                <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-4">
                    <a href="{{ url('/robots.txt') }}" target="_blank" rel="noopener">
                        {{ url('/robots.txt') }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ url('/sitemap.xml') }}" target="_blank" rel="noopener">
                        {{ url('/sitemap.xml') }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- 3. Social sharing --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Social sharing</h2>

            <div class="mb-3">
                <label for="seo_og_title" class="form-label small fw-semibold">Open Graph title</label>
                <input id="seo_og_title" name="seo_og_title" type="text" maxlength="180"
                       class="form-control @error('seo_og_title') is-invalid @enderror"
                       value="{{ old('seo_og_title', $settings['seo_og_title']['value'] ?? '') }}">
                @error('seo_og_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_og_description" class="form-label small fw-semibold">Open Graph description</label>
                <textarea id="seo_og_description" name="seo_og_description" rows="3" maxlength="320"
                          class="form-control @error('seo_og_description') is-invalid @enderror">{{ old('seo_og_description', $settings['seo_og_description']['value'] ?? '') }}</textarea>
                @error('seo_og_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_twitter_card" class="form-label small fw-semibold">Twitter card type</label>
                {{-- Block form, not the inline one. Laravel 12 compiled the
                     inline variant of this expression to an UNTERMINATED
                     "<?php(" with no closing tag, which silently stopped
                     Blade compiling the rest of the file — every directive
                     after it was emitted raw. It only surfaced at render
                     time, so a passing view:cache proved nothing. --}}
                @php
                    $twitterCard = old('seo_twitter_card', $settings['seo_twitter_card']['value'] ?? 'summary_large_image');
                @endphp
                <select id="seo_twitter_card" name="seo_twitter_card"
                        class="form-select @error('seo_twitter_card') is-invalid @enderror">
                    <option value="summary" @selected($twitterCard === 'summary')>Summary</option>
                    <option value="summary_large_image" @selected($twitterCard === 'summary_large_image')>Summary with large image</option>
                </select>
                @error('seo_twitter_card')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="seo_twitter_site" class="form-label small fw-semibold">Twitter/X site handle</label>
                <input id="seo_twitter_site" name="seo_twitter_site" type="text" maxlength="40"
                       class="form-control @error('seo_twitter_site') is-invalid @enderror"
                       value="{{ old('seo_twitter_site', $settings['seo_twitter_site']['value'] ?? '') }}"
                       aria-describedby="seo_twitter_site-help">
                <div id="seo_twitter_site-help" class="form-text small">
                    Your X/Twitter handle, e.g. @iconors
                </div>
                @error('seo_twitter_site')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <p class="form-text small mb-0">
                The social sharing image is set under Branding &rarr; Social share image.
            </p>
        </div>

        {{-- 4. Structured data --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Structured data</h2>

            <div class="mb-3">
                <label for="seo_organisation_name" class="form-label small fw-semibold">Organisation name</label>
                <input id="seo_organisation_name" name="seo_organisation_name" type="text" maxlength="120"
                       class="form-control @error('seo_organisation_name') is-invalid @enderror"
                       value="{{ old('seo_organisation_name', $settings['seo_organisation_name']['value'] ?? '') }}">
                @error('seo_organisation_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="seo_social_profiles" class="form-label small fw-semibold">Social profiles</label>
                <textarea id="seo_social_profiles" name="seo_social_profiles" rows="4" maxlength="2000"
                          class="form-control @error('seo_social_profiles') is-invalid @enderror"
                          aria-describedby="seo_social_profiles-help">{{ old('seo_social_profiles', $settings['seo_social_profiles']['value'] ?? '') }}</textarea>
                <div id="seo_social_profiles-help" class="form-text small">
                    One social profile URL per line. Used for Organization sameAs structured data.
                </div>
                @error('seo_social_profiles')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- 5. Sitemaps --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Sitemaps</h2>

            {{-- The array is bound to a variable first, on purpose. Blade's
                 directive parser cannot handle a multi-line array literal
                 written inline in the loop signature: it mis-detects the
                 closing bracket, compiles without complaint, and then dies
                 at render time with "unexpected endforeach". Same trap as
                 a multi-line array in the json directive. --}}
            @php
                $sitemapToggles = [
                    'seo_sitemap_enabled'      => 'Enable XML sitemap',
                    'seo_sitemap_assets'       => 'Include assets',
                    'seo_sitemap_categories'   => 'Include categories',
                    'seo_sitemap_contributors' => 'Include contributor profiles',
                ];
            @endphp

            @foreach ($sitemapToggles as $key => $label)
                <div class="mb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="{{ $key }}" name="{{ $key }}" value="1"
                               @checked(old($key, $settings[$key]['value'] ?? true))>
                        <label class="form-check-label small fw-semibold" for="{{ $key }}">{{ $label }}</label>
                    </div>
                    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>

        {{-- 6. Site verification --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Site verification</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="seo_google_verification" class="form-label small fw-semibold">Google verification</label>
                    <input id="seo_google_verification" name="seo_google_verification" type="text" maxlength="120"
                           class="form-control @error('seo_google_verification') is-invalid @enderror"
                           value="{{ old('seo_google_verification', $settings['seo_google_verification']['value'] ?? '') }}"
                           aria-describedby="seo-verification-help">
                    @error('seo_google_verification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="seo_bing_verification" class="form-label small fw-semibold">Bing verification</label>
                    <input id="seo_bing_verification" name="seo_bing_verification" type="text" maxlength="120"
                           class="form-control @error('seo_bing_verification') is-invalid @enderror"
                           value="{{ old('seo_bing_verification', $settings['seo_bing_verification']['value'] ?? '') }}"
                           aria-describedby="seo-verification-help">
                    @error('seo_bing_verification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div id="seo-verification-help" class="form-text small mt-1">
                Paste only the content value from the meta tag, not the whole tag.
            </div>
        </div>

        {{-- 7. Analytics --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Analytics</h2>

            @foreach ($analyticsProviders as $provider => $cfg)
                @php
                    $enabledKey = "analytics_{$provider}_enabled";
                    $idKey      = $cfg['id_key'];
                @endphp
                <div class="row g-3 align-items-start pb-3 mb-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                    <div class="col-md-5">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="{{ $enabledKey }}" name="{{ $enabledKey }}" value="1"
                                   @checked(old($enabledKey, $settings[$enabledKey]['value'] ?? false))>
                            <label class="form-check-label small fw-semibold" for="{{ $enabledKey }}">{{ $cfg['label'] }}</label>
                        </div>
                        @error($enabledKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-7">
                        <label for="{{ $idKey }}" class="form-label small fw-semibold">{{ $cfg['id_label'] }}</label>
                        <input id="{{ $idKey }}" name="{{ $idKey }}" type="text"
                               placeholder="{{ $cfg['placeholder'] }}"
                               class="form-control @error($idKey) is-invalid @enderror"
                               value="{{ old($idKey, $settings[$idKey]['value'] ?? '') }}">
                        @error($idKey)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endforeach

            <div class="alert alert-info small mb-0" role="alert">
                A disabled provider loads nothing at all — no script tag, no network request.
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save SEO settings</button>
    </form>

@endsection

@push('scripts')
    <script>
        (function () {
            var field = document.getElementById('seo_meta_description');
            var counter = document.querySelector('[data-char-counter]');
            if (!field || !counter) return;

            field.addEventListener('input', function () {
                counter.textContent = field.value.length + ' / 320';
            });
        })();
    </script>
@endpush
