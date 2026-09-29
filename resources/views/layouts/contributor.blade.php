@inject('settings', 'App\Services\Settings\SettingsService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Contributor') — {{ $settings->get('site_name', 'Iconors') }}</title>

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
<a class="skip-link" href="#contributor-main">Skip to main content</a>

@php
    $contributor = auth()->user()->contributor;

    $pending = \App\Models\Asset::where('contributor_id', $contributor?->id)
        ->whereIn('status', ['changes_requested'])->count();

    $nav = [
        ['heading' => 'Studio'],
        ['label' => 'Overview',  'icon' => 'speedometer2',  'route' => 'contributor.dashboard'],
        ['label' => 'My assets', 'icon' => 'collection',    'route' => 'contributor.assets.index',
         'badge' => $pending ?: null],
        ['label' => 'Upload',    'icon' => 'cloud-arrow-up','route' => 'contributor.assets.create'],
        ['label' => 'Analytics', 'icon' => 'graph-up',      'route' => 'contributor.analytics'],

        ['heading' => 'Money'],
        ['label' => 'Earnings',  'icon' => 'cash-stack',    'route' => 'contributor.earnings'],

        ['heading' => 'Account'],
        ['label' => 'Profile',   'icon' => 'person-badge',  'route' => 'contributor.profile'],
    ];
@endphp

<div class="admin-shell">
    <aside class="admin-sidebar" id="contributorSidebar" aria-label="Contributor navigation">
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
            <a class="admin-nav__link" href="{{ route('assets.index') }}">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <span>View marketplace</span>
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

    <div class="admin-backdrop" data-admin-close hidden></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-ico-ghost btn-sm d-lg-none" type="button"
                    data-admin-toggle aria-controls="contributorSidebar" aria-expanded="false"
                    aria-label="Open contributor navigation">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>

            <div class="flex-grow-1 min-w-0">
                <h1 class="admin-topbar__title">@yield('title', 'Overview')</h1>
                @hasSection('subtitle')
                    <p class="admin-topbar__subtitle">@yield('subtitle')</p>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-ico-primary btn-sm d-none d-md-inline-flex"
                   href="{{ route('contributor.assets.create') }}">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Upload
                </a>
                <button class="btn btn-ico-ghost btn-sm" type="button"
                        data-ico-theme-toggle aria-pressed="true" aria-label="Toggle dark mode">
                    <i class="bi bi-circle-half" aria-hidden="true"></i>
                </button>
                <span class="admin-user">
                    <span class="admin-user__avatar" aria-hidden="true">
                        {{ Str::upper(Str::substr($contributor?->display_name ?? auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="d-none d-md-block">
                        <span class="admin-user__name">{{ $contributor?->display_name ?? auth()->user()->name }}</span>
                        <span class="admin-user__role">
                            Contributor
                            @if ($contributor?->is_verified)
                                <i class="bi bi-patch-check-fill ms-1" style="color:var(--ico-primary)"
                                   aria-hidden="true"></i><span class="visually-hidden">Verified</span>
                            @endif
                        </span>
                    </span>
                </span>
            </div>
        </header>

        <main class="admin-content" id="contributor-main">
            @if (session('status'))
                <div class="alert alert-success d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                    <div>{{ session('status') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
(function () {
    var sidebar = document.getElementById('contributorSidebar');
    var backdrop = document.querySelector('.admin-backdrop');
    var toggle = document.querySelector('[data-admin-toggle]');
    if (!sidebar || !toggle) return;

    function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('is-open')); });
    backdrop.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();
</script>

@stack('scripts')
</body>
</html>
