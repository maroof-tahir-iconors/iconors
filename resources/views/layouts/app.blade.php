@inject('settings', 'App\Services\Settings\SettingsService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $settings->get('site_name', 'Iconors'))</title>
    <meta name="description" content="@yield('meta_description', $settings->get('site_description'))">

    {{-- Matches the live Iconors brand signal. --}}
    <meta name="theme-color" content="{{ $settings->get('brand_primary', '#3CF7A2') }}">

    @if ($favicon = $settings->get('favicon'))
        <link rel="icon" href="{{ Storage::disk('public')->url($favicon) }}">
    @endif

    @if ($ogImage = $settings->get('social_share_image'))
        <meta property="og:image" content="{{ Storage::disk('public')->url($ogImage) }}">
    @endif
    <meta property="og:site_name" content="{{ $settings->get('site_name', 'Iconors') }}">
    <meta property="og:type" content="website">

    @include('partials.seo-head')

    @stack('meta')

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    {{--
        Admin-controlled branding, injected AFTER the compiled stylesheet so
        DB values win over the SCSS defaults without a rebuild.

        brandCss() validates every value against a strict hex/unit allowlist
        before it gets here — this block cannot be Blade-escaped, because
        escaped CSS stops being CSS, so the sanitising must happen upstream.
    --}}
    @if ($brandCss = $settings->brandCss())
        <style id="ico-brand-tokens">{!! $brandCss !!}</style>
    @endif

    {{-- Set colour mode before first paint to avoid a flash of the wrong
         theme. Dark is the Iconors default; a stored preference always wins. --}}
    
    <script>
        (function () {
            try {
                var t = localStorage.getItem('ico-theme') || 'dark';
                document.documentElement.setAttribute('data-bs-theme', t);
            } catch (e) {}
        })();
    </script>

    <!-- Google AdSense verification code -->
    <script async
        src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=YOUR_ACTUAL_PUBLISHER_ID"
        crossorigin="anonymous"></script>
</head>

<body>
    {{-- Brief §36: first focusable element on the page. --}}
    <a class="skip-link" href="#main">Skip to main content</a>

    @include('partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>
