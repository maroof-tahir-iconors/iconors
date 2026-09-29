@extends('layouts.app')

@section('title', ($page->title ?? 'About Iconors').' — Iconors')
@section('meta_description', 'Iconors is a marketplace for icons, animations, illustrations and stickers, built by independent designers and a small team.')

@php
    // Media slots read from settings first, so imagery can be swapped from
    // the admin dashboard without editing this file.
    $settingsService = app(App\Services\Settings\SettingsService::class);

    $heroImage = $settingsService->get('about_image_primary')
        ? Storage::disk('public')->url($settingsService->get('about_image_primary'))
        : asset('storage/pages/about-team.jpg');

    $bodyImage = $settingsService->get('about_image_secondary')
        ? Storage::disk('public')->url($settingsService->get('about_image_secondary'))
        : asset('storage/pages/about-craft.jpg');
@endphp

@section('content')

{{-- ---------------------------------------------------------------
     Hero — text left, image right
--------------------------------------------------------------- --}}
<section class="ico-hero" data-ico-hero>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(38vw,420px);aspect-ratio:1;background:var(--ico-primary);top:-16%;left:-10%"></div>

    <div class="container-ico position-relative">
        <div class="split">
            <div class="split__body">
                <span class="ico-hero__label" data-ico-hero-item>About</span>
                <h1 class="ico-hero__title mb-3" data-ico-hero-item style="font-size:clamp(2rem,4.4vw,3.2rem)">
                    {{ $page->title ?? 'About Iconors' }}
                </h1>
                <p class="ico-hero__lead mb-4" data-ico-hero-item style="margin-inline:0">
                    A marketplace for the small visual pieces that make products feel finished —
                    icons, animations, illustrations, stickers and emojis.
                </p>
                <div class="d-flex flex-wrap gap-2" data-ico-hero-item>
                    <a class="btn btn-ico-primary" href="{{ route('assets.index') }}">Browse the library</a>
                    <a class="btn btn-ico-outline" href="{{ route('contributor.apply') }}">Become a contributor</a>
                </div>
            </div>

            <div class="split__media" data-ico-hero-item>
                <img src="{{ $heroImage }}"
                     alt="A design team collaborating around a laptop"
                     loading="eager" fetchpriority="high" decoding="async"
                     width="1400" height="1050">
                <span class="split__chip">
                    <span class="split__chip-icon" aria-hidden="true"><i class="bi bi-collection-fill"></i></span>
                    <span>
                        <span class="split__chip-value d-block">{{ number_format($stats['assets']) }}</span>
                        <span class="split__chip-label">Assets, human-reviewed</span>
                    </span>
                </span>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Stats
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <h2 class="visually-hidden">Iconors by the numbers</h2>
        <div class="ico-stats pt-0">
            <div>
                <div class="ico-stats__value">{{ number_format($stats['assets']) }}</div>
                <div class="ico-stats__label">Creative assets</div>
            </div>
            <div>
                <div class="ico-stats__value">{{ number_format($stats['contributors']) }}</div>
                <div class="ico-stats__label">Contributors</div>
            </div>
            <div>
                <div class="ico-stats__value">{{ number_format($stats['downloads']) }}</div>
                <div class="ico-stats__label">Downloads</div>
            </div>
            <div>
                <div class="ico-stats__value">{{ number_format($stats['categories']) }}</div>
                <div class="ico-stats__label">Categories</div>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Editorial content
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="split split--reverse">
            <div class="split__body">
                <div class="prose">
                    @if ($page)
                        {!! $page->content !!}
                    @else
                        <p class="lead">Iconors is a marketplace for the small visual pieces that make products feel
                            finished — icons, animations, illustrations, stickers and emojis.</p>
                        <p>This page has not been published yet. Check back soon for the full story.</p>
                    @endif
                </div>
            </div>

            <div class="split__media">
                <img src="{{ $bodyImage }}"
                     alt="Close-up of a designer sketching interface icons"
                     loading="lazy" decoding="async"
                     width="1400" height="1050">
                <span class="split__chip">
                    <span class="split__chip-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
                    <span>
                        <span class="split__chip-value d-block">{{ number_format($stats['contributors']) }}</span>
                        <span class="split__chip-label">Independent contributors</span>
                    </span>
                </span>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Catalogue showcase

     Real assets from our own library rather than stock photography:
     on-brand, no third-party licence question, no outbound dependency,
     and it doubles as an honest sample of what is actually on offer.
--------------------------------------------------------------- --}}
@if (($showcase ?? collect())->isNotEmpty())
<section class="section--tight" data-ico-reveal>
    <div class="container-ico text-center">
        <h2 class="h5 mb-1">A few things from the library</h2>
        <p class="small mb-4" style="color:var(--ico-muted)">
            Every asset is reviewed by a person before it is published.
        </p>

        <div class="asset-strip">
            @foreach ($showcase as $item)
                @php
                    $preview = $item->previews->firstWhere('is_primary', true) ?? $item->previews->first();
                @endphp
                @continue(! $preview)
                <a class="asset-strip__item" href="{{ route('assets.show', $item->slug) }}"
                   title="{{ $item->title }}">
                    <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                         alt="{{ $item->title }}" loading="lazy" decoding="async"
                         width="72" height="72">
                </a>
            @endforeach
        </div>

        <a class="btn btn-ico-outline btn-sm mt-4" href="{{ route('assets.index') }}">
            Browse the full catalogue
        </a>
    </div>
</section>
@endif

{{-- ---------------------------------------------------------------
     Final CTA
--------------------------------------------------------------- --}}
<section class="section" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-cta">
            <h2>Explore what Iconors has to offer</h2>
            <p>Browse the library, or join as a contributor and start earning from your work.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary" href="{{ route('assets.index') }}">Browse assets</a>
                <a class="btn btn-light" href="{{ route('contributor.apply') }}">Become a contributor</a>
            </div>
        </div>
    </div>
</section>

@endsection
