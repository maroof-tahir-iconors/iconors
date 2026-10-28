@extends('layouts.app')
@inject('settings', 'App\Services\Settings\SettingsService')

@section('title', $settings->get('site_name').' — '.$settings->get('site_tagline'))

@section('content')

{{-- ---------------------------------------------------------------
     Hero
--------------------------------------------------------------- --}}
<section class="ico-hero" data-ico-hero>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(46vw,480px);aspect-ratio:1;background:var(--ico-primary);top:-14%;left:-8%"></div>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(40vw,420px);aspect-ratio:1;background:var(--ico-accent);top:4%;right:-8%"></div>

    {{-- Real catalogue tiles drifting behind the headline. Decorative, so
         aria-hidden, and hidden entirely below lg where they would crowd
         the text. --}}
    @if ($floating->isNotEmpty())
        <div class="hero-float" aria-hidden="true">
            @foreach ($floating as $asset)
                @php
                    $preview = $asset->previews->firstWhere('is_primary', true) ?? $asset->previews->first();
                @endphp
                @continue(! $preview)
                <span class="hero-float__item" data-ico-parallax>
                    <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                         alt="" loading="lazy" decoding="async" width="84" height="84">
                </span>
            @endforeach
        </div>
    @endif

    <div class="container-ico text-center position-relative">
        <span class="ico-hero__label" data-ico-hero-item>Design marketplace</span>

        <h1 class="ico-hero__title" data-ico-hero-item>
            All your creative assets<br>
            <span class="ico-hero__gradient-text">in one place</span>
        </h1>

        <p class="ico-hero__lead mb-4" data-ico-hero-item>
            {{ $settings->get('site_description') }}
        </p>

        <form class="ico-search ico-search--lg mx-auto mb-3" style="max-width:660px"
              action="{{ route('assets.index') }}" method="GET" role="search" data-ico-hero-item>
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="hero-search" class="visually-hidden">Search assets</label>
            <input id="hero-search" type="search" name="q"
                   placeholder="Search icons, illustrations, 3D, animations…">
            <button class="btn btn-ico-primary btn-magnetic" type="submit" data-ico-magnetic>Search</button>
        </form>

        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-4" data-ico-hero-item>
            <span class="small" style="color:var(--ico-muted)">Trending:</span>
            @foreach (['icons', 'animation', 'business', '3d', 'social media'] as $term)
                <a class="btn btn-ico-outline btn-sm py-1 px-2" style="font-size:.78rem"
                   href="{{ route('assets.index', ['q' => $term]) }}">{{ $term }}</a>
            @endforeach
        </div>

        <div class="ico-stats pt-2" data-ico-hero-item>
            @foreach ([
                ['value' => $stats['assets'],       'label' => 'Creative assets'],
                ['value' => $stats['contributors'], 'label' => 'Contributors'],
                ['value' => $stats['downloads'],    'label' => 'Downloads'],
            ] as $stat)
                <div>
                    <div class="ico-stats__value" data-ico-count="{{ $stat['value'] }}">
                        {{ number_format($stat['value']) }}
                    </div>
                    <div class="ico-stats__label">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Marquee — two strips moving in opposite directions
--------------------------------------------------------------- --}}
@if (count($marqueeTop) || count($marqueeBottom))
    <section class="section--tight pt-0" aria-label="Browse by category and tag">
        @if (count($marqueeTop))
            <x-marquee :items="$marqueeTop" />
        @endif

        @if (count($marqueeBottom))
            <div class="mt-3">
                <x-marquee :items="$marqueeBottom" :reverse="true" />
            </div>
        @endif
    </section>
@endif

{{-- ---------------------------------------------------------------
     Asset types
--------------------------------------------------------------- --}}

@php
    $typeOrder = [
        'svg-icons',
        'illustrations',
        'lottie-animations',
        '3d-assets',
    ];

    $assetTypes = $assetTypes->sortBy(function ($type) use ($typeOrder) {
        $position = array_search($type->slug, $typeOrder, true);

        return $position === false
            ? count($typeOrder)
            : $position;
    })->values();
@endphp

                    
@if ($settings->get('home_show_types', true) && $assetTypes->isNotEmpty())
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Browse by type</h2>
                <p>Every format a product team needs.</p>
            </div>
        </div>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-8 g-2 g-md-3">
            @foreach ($assetTypes as $type)
                <div class="col">
                    <a class="ico-card type-tile text-decoration-none" data-ico-tilt
                       href="{{ route('assets.index', ['type' => $type->slug]) }}">
                        <span class="type-tile__icon" aria-hidden="true">
                            <i class="bi bi-{{ $type->icon ?: 'collection' }}"></i>
                        </span>
                        <span class="type-tile__label">{{ $type->name }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ---------------------------------------------------------------
     Categories
--------------------------------------------------------------- --}}
@if ($settings->get('home_show_categories', true) && $categories->isNotEmpty())
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Popular categories</h2>
                <p>Jump straight to what you are working on.</p>
            </div>
            <a class="section-head__link" href="{{ route('assets.index') }}">
                View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-2 g-md-3">
            @foreach ($categories as $category)
                <div class="col">
                    <a class="ico-card category-tile text-decoration-none" data-ico-tilt
                       href="{{ route('assets.index', ['category' => $category->slug]) }}">
                        <div class="category-tile__name">{{ $category->name }}</div>
                        <div class="category-tile__count">
                            {{ number_format($category->assets_count) }}
                            {{ Str::plural('asset', $category->assets_count) }}
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ---------------------------------------------------------------
     Asset rails, as carousels

     Swiper is dynamically imported and only when one of these is on the
     page, so other routes never pay for it.
--------------------------------------------------------------- --}}
@php
    $rails = [
        ['key' => 'trending', 'title' => 'Trending now',   'blurb' => 'What designers are downloading this week.', 'items' => $trending, 'link' => ['sort' => 'trending']],
        ['key' => 'newest',   'title' => 'Newly added',    'blurb' => 'Fresh from our contributors.',              'items' => $newest,   'link' => ['sort' => 'newest']],
        ['key' => 'free',     'title' => 'Free assets',    'blurb' => 'Download without a subscription.',          'items' => $free,     'link' => ['price' => 'free']],
        ['key' => 'premium',  'title' => 'Premium assets', 'blurb' => 'Our highest-quality work.',                 'items' => $premium,  'link' => ['price' => 'premium']],
    ];
@endphp

@foreach ($rails as $railIndex => $rail)
    @continue($rail['items']->isEmpty())

    <section class="section--tight" @if($railIndex > 0) data-ico-reveal @endif>
        <div class="container-ico">
            <div class="section-head">
                <div>
                    <h2>{{ $rail['title'] }}</h2>
                    <p>{{ $rail['blurb'] }}</p>
                </div>
                <a class="section-head__link" href="{{ route('assets.index', $rail['link']) }}">
                    View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xxl-6 g-3">
                @foreach ($rail['items']->take(6) as $asset)
                    {{-- Computed outside the tag on purpose: a `<` inside a
                         component attribute breaks Blade's component parser
                         and fails as "unexpected end of file", pointing
                         nowhere near the cause. --}}
                    @php
                        $eager = $railIndex === 0;
                    @endphp
                    <div class="col">
                        <x-asset-card :asset="$asset" :eager="$eager" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endforeach

{{-- ---------------------------------------------------------------
     How it works
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>How Iconors works</h2>
                <p>From search to production in three steps.</p>
            </div>
        </div>

        @php
            $steps = [
                ['icon' => 'search',        'title' => 'Find it',      'text' => 'Search across icons, animations, illustrations and stickers, filtered by style, format and licence.'],
                ['icon' => 'sliders',       'title' => 'Make it yours','text' => 'Recolour, resize and export in the browser — no design tool required.'],
                ['icon' => 'cloud-arrow-down','title' => 'Ship it',    'text' => 'Download the source file under a licence that is spelled out before you commit.'],
            ];
        @endphp

        <div class="row row-cols-1 row-cols-md-3 g-3">
            @foreach ($steps as $step)
                <div class="col">
                    <div class="ico-card p-4 h-100" data-ico-tilt>
                        <span class="type-tile__icon mb-3" aria-hidden="true">
                            <i class="bi bi-{{ $step['icon'] }}"></i>
                        </span>
                        <h3 class="h6 mb-2">{{ $step['title'] }}</h3>
                        <p class="small mb-0" style="color:var(--ico-muted)">{{ $step['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Contributors — off by default, toggle in Settings → Marketplace
--------------------------------------------------------------- --}}
@if ($settings->get('home_show_contributors', false) && $contributors->isNotEmpty())
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Featured contributors</h2>
                <p>The designers behind the collection.</p>
            </div>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
            @foreach ($contributors as $contributor)
                <div class="col">
                    <a class="ico-card contributor-tile text-decoration-none"
                       href="{{ route('assets.index', ['contributor' => $contributor->slug]) }}">
                        <span class="contributor-tile__avatar" aria-hidden="true">
                            {{ Str::upper(Str::substr($contributor->display_name, 0, 1)) }}
                        </span>
                        <div class="contributor-tile__name text-truncate">{{ $contributor->display_name }}</div>
                        <div class="contributor-tile__count">
                            {{ number_format($contributor->assets_count) }} assets
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ---------------------------------------------------------------
     Final CTA
--------------------------------------------------------------- --}}
<section class="section" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-cta">
            {{-- White on the deep purple ground: 18.4:1. --}}
            <h2>Start creating with {{ $settings->get('site_name', 'Iconors') }}</h2>
            <p>Thousands of icons, animations and stickers — free and premium.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary btn-magnetic" data-ico-magnetic
                   href="{{ route('assets.index') }}">Browse assets</a>
                @guest
                    <a class="btn btn-light" href="{{ route('register') }}">Create free account</a>
                @endguest
            </div>
        </div>
    </div>
</section>

@endsection
