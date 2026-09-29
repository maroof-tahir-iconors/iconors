@inject('settings', 'App\Services\Settings\SettingsService')

{{--
    SEO head — brief §29.

    Everything here is admin-controlled. Values are validated on save
    against each provider's real ID format, because these strings are
    interpolated into inline script blocks: a free-text analytics ID
    would be script injection on every page of the site.
--}}

@php
    $indexable = (bool) $settings->get('seo_allow_indexing', true);
    $canonical = $settings->get('seo_canonical_host');
@endphp

{{-- The master switch. Off => every page tells crawlers to stay away. --}}
@if (! $indexable)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="@yield('robots', 'index, follow')">
@endif

@if ($keywords = $settings->get('seo_meta_keywords'))
    <meta name="keywords" content="{{ $keywords }}">
@endif

{{-- Canonical. Falls back to the current URL without its query string, so
     filtered listings do not each register as a separate duplicate page. --}}
<link rel="canonical" href="{{ $canonical ? rtrim($canonical, '/').request()->getPathInfo() : url()->current() }}">

<meta property="og:title" content="@yield('og_title', $settings->get('seo_og_title') ?: $settings->get('seo_default_title'))">
<meta property="og:description" content="@yield('og_description', $settings->get('seo_og_description') ?: $settings->get('seo_meta_description'))">
<meta property="og:url" content="{{ url()->current() }}">

<meta name="twitter:card" content="{{ $settings->get('seo_twitter_card', 'summary_large_image') }}">
@if ($handle = $settings->get('seo_twitter_site'))
    <meta name="twitter:site" content="{{ Str::start($handle, '@') }}">
@endif

@if ($google = $settings->get('seo_google_verification'))
    <meta name="google-site-verification" content="{{ $google }}">
@endif
@if ($bing = $settings->get('seo_bing_verification'))
    <meta name="msvalidate.01" content="{{ $bing }}">
@endif

{{-- Organisation + WebSite/SearchAction structured data (brief §29).
     Emitted site-wide; individual pages add their own types on top. --}}
@php
    $profiles = array_values(array_filter(array_map(
        'trim',
        preg_split('/[\r\n,]+/', (string) $settings->get('seo_social_profiles')) ?: []
    )));

    $orgSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => $settings->get('seo_organisation_name') ?: $settings->get('site_name'),
        'url'      => url('/'),
        'logo'     => $settings->get('logo_main')
            ? Storage::disk('public')->url($settings->get('logo_main'))
            : null,
        'sameAs'   => $profiles ?: null,
    ]);

    $siteSchema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        'name'            => $settings->get('site_name'),
        'url'             => url('/'),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => route('assets.index').'?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
@endphp

<script type="application/ld+json">{!! json_encode($orgSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>

{{--
    Analytics.

    Brief §28: "Do not load disabled tracking scripts." Each block is
    behind BOTH its enable flag and a non-empty ID, so a disabled provider
    contributes no bytes and makes no requests — it is not merely inert.
--}}
@if ($settings->get('analytics_gtm_enabled') && ($gtm = $settings->get('analytics_gtm_id')))
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
    var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;
    j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtm }}');</script>
@endif

@if ($settings->get('analytics_ga_enabled') && ($ga = $settings->get('analytics_ga_id')))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $ga }}', { anonymize_ip: true });
    </script>
@endif

@if ($settings->get('analytics_meta_enabled') && ($pixel = $settings->get('analytics_meta_pixel_id')))
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pixel }}');fbq('track','PageView');</script>
@endif

@if ($settings->get('analytics_clarity_enabled') && ($clarity = $settings->get('analytics_clarity_id')))
    <script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script','{{ $clarity }}');</script>
@endif

@if ($settings->get('analytics_plausible_enabled') && ($domain = $settings->get('analytics_plausible_domain')))
    <script defer data-domain="{{ $domain }}" src="https://plausible.io/js/script.js"></script>
@endif
