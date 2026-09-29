@inject('settings', 'App\Services\Settings\SettingsService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Admin') — {{ $settings->get('site_name', 'Iconors') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    @if ($brandCss = $settings->brandCss())
        <style id="ico-brand-tokens">{!! $brandCss !!}</style>
    @endif

    <script>
        (function () {
            try {
                document.documentElement.setAttribute(
                    'data-bs-theme', localStorage.getItem('ico-theme') || 'dark'
                );
            } catch (e) {}
        })();
    </script>
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">Skip to main content</a>

@php
    // Menu is filtered by PERMISSION, so a Finance Manager never sees a
    // moderation link they cannot use. This is presentation only — every
    // route re-checks server-side (brief §26).
    $nav = [
        ['heading' => 'Overview'],
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'admin.dashboard', 'can' => null],

        ['heading' => 'Moderation'],
        ['label' => 'Asset review',  'icon' => 'inbox',        'route' => 'admin.assets.review', 'can' => 'assets.approve',
         'badge' => \App\Models\Asset::whereIn('status', ['submitted','under_review'])->count()],
        ['label' => 'Applications',  'icon' => 'person-plus',  'route' => 'admin.applications',  'can' => 'contributors.view',
         'badge' => \App\Models\ContributorApplication::whereIn('status', ['pending','under_review'])->count()],

        ['heading' => 'People'],
        ['label' => 'Users', 'icon' => 'people', 'route' => 'admin.users.index', 'can' => 'users.view'],

        ['heading' => 'Commerce'],
        ['label' => 'Plans',     'icon' => 'credit-card-2-front', 'route' => 'admin.plans.index',    'can' => 'subscriptions.edit.all'],
        ['label' => 'Licences',  'icon' => 'file-earmark-text',   'route' => 'admin.licenses.index', 'can' => 'taxonomies.edit.all'],

        ['heading' => 'Site'],
        ['label' => 'General',     'icon' => 'gear',      'route' => 'admin.settings.general',     'can' => 'settings.manage'],
        ['label' => 'Branding',    'icon' => 'palette',   'route' => 'admin.settings.branding',    'can' => 'branding.manage'],
        ['label' => 'SEO',         'icon' => 'graph-up',  'route' => 'admin.settings.seo',         'can' => 'seo.manage'],

        ['heading' => 'Settings'],
        ['label' => 'Payments',    'icon' => 'wallet2',   'route' => 'admin.settings.payments',    'can' => 'payments.manage'],
        ['label' => 'Royalties',   'icon' => 'percent',   'route' => 'admin.settings.royalties',   'can' => 'payouts.manage'],
        ['label' => 'Marketplace', 'icon' => 'sliders',   'route' => 'admin.settings.marketplace', 'can' => 'settings.manage'],
        ['label' => 'Email',       'icon' => 'envelope',  'route' => 'admin.settings.mail',        'can' => 'smtp.manage'],
    ];
@endphp

<div class="admin-shell">

    {{-- Sidebar --}}
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
        <a class="ico-logo admin-sidebar__brand" href="{{ route('home') }}">
            <span class="ico-logo__mark" aria-hidden="true">
                <svg width="19" height="19" viewBox="0 0 28 28" fill="none">
                    <path d="M5.4 5.3h3.6v3.6H5.4zM23.4 12h-3.3c-.2-1.7-1.5-3-3.3-3.2h-3.6V5.3h5.2c2.2 0 4 1.8 4 4zM23.4 15.4v3.8c0 2.1-1.8 3.6-3.9 3.6H10c-2.2 0-3.9-1.7-3.9-3.8V12h3.5v3.3c0 2.2 1.7 3.8 3.9 3.8h2.6c2.1 0 3.6-1.4 3.8-3.4z"
                          fill="currentColor"/>
                </svg>
            </span>
            <span>{{ $settings->get('site_name', 'Iconors') }}</span>
        </a>

        <nav class="admin-nav">
            @foreach ($nav as $item)
                @if (isset($item['heading']))
                    <p class="admin-nav__heading">{{ $item['heading'] }}</p>
                    @continue
                @endif

                @continue($item['can'] && ! auth()->user()->can($item['can']))

                @php
                    $active = request()->routeIs($item['route']);
                @endphp
                <a class="admin-nav__link {{ $active ? 'is-active' : '' }}"
                   href="{{ route($item['route']) }}"
                   @if($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="admin-nav__badge">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="admin-sidebar__foot">
            <a class="admin-nav__link" href="{{ route('home') }}">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span>View site</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="admin-nav__link w-100 border-0 bg-transparent text-start" type="submit">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    <span>Log out</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Backdrop for the mobile drawer --}}
    <div class="admin-backdrop" data-admin-close hidden></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-ico-ghost btn-sm d-lg-none" type="button"
                    data-admin-toggle aria-controls="adminSidebar" aria-expanded="false"
                    aria-label="Open admin navigation">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>

            <div class="flex-grow-1 min-w-0">
                <h1 class="admin-topbar__title">@yield('title', 'Dashboard')</h1>
                @hasSection('subtitle')
                    <p class="admin-topbar__subtitle">@yield('subtitle')</p>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-ico-ghost btn-sm" type="button"
                        data-ico-theme-toggle aria-pressed="true" aria-label="Toggle dark mode">
                    <i class="bi bi-circle-half" aria-hidden="true"></i>
                </button>
                <span class="admin-user">
                    <span class="admin-user__avatar" aria-hidden="true">
                        {{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="d-none d-md-block">
                        <span class="admin-user__name">{{ auth()->user()->name }}</span>
                        <span class="admin-user__role">{{ auth()->user()->getRoleNames()->first() ?? 'Staff' }}</span>
                    </span>
                </span>
            </div>
        </header>

        <main class="admin-content" id="admin-main">
            @if (session('status'))
                <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                    <div>{{ session('warning') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
// Mobile drawer. Kept inline and dependency-free so the admin chrome
// works even if the main bundle fails.
(function () {
    var sidebar = document.getElementById('adminSidebar');
    var backdrop = document.querySelector('.admin-backdrop');
    var toggle = document.querySelector('[data-admin-toggle]');
    if (!sidebar || !toggle) return;

    function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
    });
    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>

@stack('scripts')
</body>
</html>
