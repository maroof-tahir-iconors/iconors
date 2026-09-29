@extends('layouts.app')
@inject('settings', 'App\Services\Settings\SettingsService')

@section('title', ($page->title ?? 'Careers at Iconors').' — Iconors')
@section('meta_description', 'Careers at Iconors — a small, remote-first team building a marketplace for icons, animations and illustrations.')

@php
    $support = $settings->get('support_email', 'support@iconors.com');

    // Media slots read from settings first, so imagery can be swapped from
    // the admin dashboard without editing this file. The downloaded files
    // are only the fallback.
    $heroImage = $settings->get('careers_image_primary')
        ? Storage::disk('public')->url($settings->get('careers_image_primary'))
        : asset('storage/pages/careers-remote.jpg');

    $bodyImage = $settings->get('careers_image_secondary')
        ? Storage::disk('public')->url($settings->get('careers_image_secondary'))
        : asset('storage/pages/careers-desk.jpg');

    $hiringSteps = [
        ['title' => 'Apply',        'body' => 'Send us your work and a short note about what you would want to do here.'],
        ['title' => 'Conversation', 'body' => 'A call with the team you would join — no trick questions, just a real conversation about the work.'],
        ['title' => 'Decision',     'body' => 'We tell you either way within a week, with specific feedback if it is a no.'],
    ];
@endphp

@section('content')

{{-- ---------------------------------------------------------------
     Hero — text left, image right
--------------------------------------------------------------- --}}
<section class="ico-hero" data-ico-hero>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(38vw,400px);aspect-ratio:1;background:var(--ico-accent);top:-14%;right:-8%"></div>

    <div class="container-ico position-relative">
        <div class="split">
            <div class="split__body">
                <span class="ico-hero__label" data-ico-hero-item>Careers</span>
                <h1 class="ico-hero__title mb-3" data-ico-hero-item style="font-size:clamp(2rem,4.4vw,3.2rem)">
                    Build the library<br>
                    <span class="ico-hero__gradient-text">designers reach for</span>
                </h1>
                <p class="ico-hero__lead mb-4" data-ico-hero-item style="margin-inline:0">
                    A small, remote-first team building a marketplace used by designers and developers every day.
                </p>
                <div class="d-flex flex-wrap gap-2" data-ico-hero-item>
                    <a class="btn btn-ico-primary" href="mailto:{{ $support }}?subject=Working%20at%20Iconors">
                        Introduce yourself
                    </a>
                    <a class="btn btn-ico-outline" href="#how-we-hire">How we hire</a>
                </div>
            </div>

            <div class="split__media" data-ico-hero-item>
                <img src="{{ $heroImage }}"
                     alt="A designer working remotely at a laptop"
                     loading="eager" fetchpriority="high" decoding="async"
                     width="1400" height="1050">
                <span class="split__chip">
                    <span class="split__chip-icon" aria-hidden="true"><i class="bi bi-globe2"></i></span>
                    <span>
                        <span class="split__chip-value d-block">Remote-first</span>
                        <span class="split__chip-label">Work from anywhere</span>
                    </span>
                </span>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Editorial content — image on the opposite side so the page
     alternates rather than repeating the same rhythm
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="split split--reverse">
            <div class="split__body">
                <div class="prose">
                    @if ($page)
                        {!! $page->content !!}
                    @else
                        <p class="lead">We are a small team building a marketplace used by designers and developers
                            every day.</p>
                        <p>This page has not been published yet. Check back soon for open roles.</p>
                    @endif
                </div>
            </div>

            <div class="split__media">
                <img src="{{ $bodyImage }}"
                     alt="A desk with design tools, notebook and laptop"
                     loading="lazy" decoding="async"
                     width="1400" height="1050">
                <span class="split__chip">
                    <span class="split__chip-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
                    <span>
                        <span class="split__chip-value d-block">Small teams</span>
                        <span class="split__chip-label">Own a surface end to end</span>
                    </span>
                </span>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     How we hire
--------------------------------------------------------------- --}}
<section class="section--tight" id="how-we-hire" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-card p-4 p-md-5">
            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-5">
                    <h2 class="h4 mb-2">How we hire</h2>
                    <p class="small mb-0" style="color:var(--ico-muted)">
                        Three steps, no surprises. We tell you where you stand at every stage.
                    </p>
                </div>
                <div class="col-lg-7">
                    <ol class="step-list list-unstyled mb-0">
                        @foreach ($hiringSteps as $step)
                            <li class="step-list__item">
                                <div>
                                    <p class="step-list__title mb-0">{{ $step['title'] }}</p>
                                    <p class="step-list__text">{{ $step['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Final CTA
--------------------------------------------------------------- --}}
<section class="section" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-cta">
            <h2>Think you would be a good fit?</h2>
            <p>We are not actively hiring right now, but we read everything sent our way.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary" href="mailto:{{ $support }}?subject=Working%20at%20Iconors">
                    Email us your work
                </a>
                <a class="btn btn-light" href="{{ route('contributor.apply') }}">Become a contributor</a>
            </div>
        </div>
    </div>
</section>

@endsection
