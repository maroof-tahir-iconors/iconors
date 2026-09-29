@inject('settings', 'App\Services\Settings\SettingsService')

<header class="ico-header" data-ico-header>
    <div class="container-ico">
        <div class="ico-header__inner">

            {{-- Column 1: brand. Uses the admin-uploaded logo when one exists. --}}
            <x-brand-logo variant="main" />

            {{-- Column 2: search. Given its own grid track so it can never
                 collide with the nav — the previous flex layout was why the
                 menu wrapped onto two lines. --}}
            <form class="ico-search d-none d-lg-flex position-relative"
                  action="{{ route('assets.index') }}" method="GET" role="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="ico-search-input" class="visually-hidden">Search assets</label>
                <input id="ico-search-input" type="search" name="q"
                       value="{{ request('q') }}"
                       placeholder="Search icons, illustrations, 3D, animations…"
                       autocomplete="off" data-ico-search-input>
                <kbd class="d-none d-xl-inline" aria-hidden="true">Ctrl K</kbd>
                <button class="btn btn-ico-primary btn-sm" type="submit">Search</button>

                <div class="position-absolute top-100 start-0 end-0 mt-2 ico-card p-0 overflow-hidden"
                     style="z-index:1040" hidden data-ico-search-results
                     role="listbox" aria-label="Search suggestions"></div>
            </form>

            {{-- Column 3: nav + account --}}
            <div class="d-flex align-items-center gap-2">
                <ul class="ico-nav d-none d-xl-flex" role="list">
                    @foreach (($navTypes ?? collect())->take(4) as $type)
                        <li>
                            <a href="{{ route('assets.index', ['type' => $type->slug]) }}"
                               @if(request('type') === $type->slug) aria-current="page" @endif>
                                {{ $type->name }}
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ route('assets.index', ['price' => 'free']) }}"
                           @if(request('price') === 'free') aria-current="page" @endif>Free</a>
                    </li>
                    <li>
                        <a href="{{ route('pricing') }}"
                           @if(request()->routeIs('pricing')) aria-current="page" @endif>Pricing</a>
                    </li>
                </ul>

                <button class="btn btn-ico-ghost btn-sm d-none d-md-inline-flex" type="button"
                        data-ico-theme-toggle aria-pressed="false" aria-label="Toggle dark mode">
                    <i class="bi bi-circle-half" aria-hidden="true"></i>
                </button>

                @auth
                    @php
                        $icoUser = auth()->user();
                        $icoPlan = $icoUser->currentPlan();
                        $icoAvatar = $icoUser->avatarUrl();
                    @endphp

                    <div class="dropdown ico-account">
                        <button class="ico-account__trigger" type="button"
                                id="icoAccountMenu" data-bs-toggle="dropdown"
                                data-bs-display="static" aria-expanded="false">
                            <span class="ico-account__avatar" aria-hidden="true">
                                @if ($icoAvatar)
                                    <img src="{{ $icoAvatar }}" alt="" width="34" height="34"
                                         loading="lazy" decoding="async">
                                @else
                                    {{ $icoUser->initials() }}
                                @endif
                            </span>
                            <span class="visually-hidden">Account menu for {{ $icoUser->name }}</span>
                            <i class="bi bi-chevron-down ico-account__caret d-none d-md-inline"
                               aria-hidden="true"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end ico-account__menu"
                             aria-labelledby="icoAccountMenu">

                            <div class="ico-account__identity">
                                <span class="ico-account__avatar ico-account__avatar--lg" aria-hidden="true">
                                    @if ($icoAvatar)
                                        <img src="{{ $icoAvatar }}" alt="" width="44" height="44"
                                             loading="lazy" decoding="async">
                                    @else
                                        {{ $icoUser->initials() }}
                                    @endif
                                </span>
                                <span class="ico-account__identity-text">
                                    <strong>{{ $icoUser->name }}</strong>
                                    <span>{{ $icoPlan?->name ?? 'Free' }} Plan</span>
                                </span>
                            </div>

                            <hr class="dropdown-divider">

                            <a class="dropdown-item" href="{{ route('dashboard') }}">
                                <i class="bi bi-grid" aria-hidden="true"></i> Go to Dashboard
                            </a>
                            <a class="dropdown-item" href="{{ route('account.downloads') }}">
                                <i class="bi bi-download" aria-hidden="true"></i> My Downloads
                            </a>
                            <a class="dropdown-item" href="{{ route('account.collections') }}">
                                <i class="bi bi-collection" aria-hidden="true"></i> My Collections
                            </a>
                            <a class="dropdown-item" href="{{ route('account.following') }}">
                                <i class="bi bi-people" aria-hidden="true"></i> My Following
                            </a>
                            <a class="dropdown-item" href="{{ route('account.members') }}">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Invite Members
                            </a>

                            @if ($icoUser->contributor && $icoUser->contributor->status === 'approved')
                                <hr class="dropdown-divider">
                                <a class="dropdown-item" href="{{ route('contributor.dashboard') }}">
                                    <i class="bi bi-easel" aria-hidden="true"></i> Contributor studio
                                </a>
                            @endif

                            @can('settings.manage')
                                <hr class="dropdown-divider">
                                <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-shield-lock" aria-hidden="true"></i> Admin dashboard
                                </a>
                            @endcan

                            <hr class="dropdown-divider">

                            {{-- POST, not a link: logging out via GET is CSRF-able
                                 and can be triggered by a prefetching browser. --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item ico-account__logout">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a class="btn btn-ico-ghost btn-sm d-none d-md-inline-flex"
                       href="{{ route('login') }}">Log in</a>
                    <a class="btn btn-ico-primary btn-sm d-none d-md-inline-flex"
                       href="{{ route('register') }}">Sign up</a>
                @endauth

                <button class="btn btn-ico-ghost btn-sm d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#icoMobileNav"
                        aria-controls="icoMobileNav" aria-label="Open menu">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</header>

{{-- Mobile off-canvas (brief §6, §35) --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="icoMobileNav" aria-labelledby="icoMobileNavLabel">
    <div class="offcanvas-header border-bottom">
        <h2 class="h6 mb-0" id="icoMobileNavLabel">{{ $settings->get('site_name', 'Iconors') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form class="ico-search mb-3" action="{{ route('assets.index') }}" method="GET" role="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="ico-search-mobile" class="visually-hidden">Search assets</label>
            <input id="ico-search-mobile" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Search assets…">
        </form>

        <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
            @foreach ($navTypes ?? [] as $type)
                <li>
                    <a class="filter-link" href="{{ route('assets.index', ['type' => $type->slug]) }}">
                        {{ $type->name }}
                    </a>
                </li>
            @endforeach
            <li><a class="filter-link" href="{{ route('assets.index', ['price' => 'free']) }}">Free assets</a></li>
            <li><a class="filter-link" href="{{ route('assets.index', ['sort' => 'trending']) }}">Trending</a></li>
            <li><hr class="my-2" style="border-color:var(--ico-border)"></li>
            <li><a class="filter-link" href="{{ route('pricing') }}">Pricing</a></li>
            <li><a class="filter-link" href="{{ route('blog') }}">Blog</a></li>
            <li><a class="filter-link" href="{{ route('about') }}">About</a></li>
            <li><a class="filter-link" href="{{ route('contributor.apply') }}">Become a contributor</a></li>
        </ul>

        @auth
            <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
                <li><a class="filter-link" href="{{ route('account.collections') }}">My Collections</a></li>
                <li><a class="filter-link" href="{{ route('account.downloads') }}">My Downloads</a></li>
                <li><a class="filter-link" href="{{ route('account.following') }}">My Following</a></li>
                <li><a class="filter-link" href="{{ route('account.details') }}">Account Details</a></li>
                <li><a class="filter-link" href="{{ route('account.subscription') }}">Subscription</a></li>
            </ul>
        @endauth

        <div class="d-grid gap-2">
            @auth
                <a class="btn btn-ico-primary" href="{{ route('dashboard') }}">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}" class="d-grid">
                    @csrf
                    <button type="submit" class="btn btn-ico-outline">Logout</button>
                </form>
            @else
                <a class="btn btn-ico-outline" href="{{ route('login') }}">Log in</a>
                <a class="btn btn-ico-primary" href="{{ route('register') }}">Create free account</a>
            @endauth
            <button class="btn btn-ico-ghost" type="button" data-ico-theme-toggle aria-pressed="false">
                <i class="bi bi-circle-half me-1" aria-hidden="true"></i> Toggle theme
            </button>
        </div>
    </div>
</div>
