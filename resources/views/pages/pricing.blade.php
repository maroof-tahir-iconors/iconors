@extends('layouts.app')
@inject('settings', 'App\Services\Settings\SettingsService')

@section('title', 'Pricing — '.$settings->get('site_name', 'Iconors'))
@section('meta_description', 'Compare Iconors plans and licences. Simple monthly or annual pricing, a '.$refundDays.'-day refund window, and no hidden fees.')

{{-- ---------------------------------------------------------------
     FAQPage structured data (brief §29).

     Built in a raw PHP block, not the json directive — a multi-line
     array literal inside a directive's parentheses compiles fine here
     and then dies at render with a confusing error, so the array is
     assembled with array_map against the $faqs variable instead.
--------------------------------------------------------------- --}}
@push('meta')
    @php
        $faqSchema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq) => [
                '@type'          => 'Question',
                'name'           => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $faq['a'],
                ],
            ], $faqs),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')

@php
    // Largest annual saving across all plans, used in the toggle label.
    // A plain PHP loop here (not a Blade @foreach over an inline array)
    // keeps this out of any directive's parentheses.
    $maxSavingPct = 0;
    foreach ($plans as $__plan) {
        $monthlyTotal = (float) $__plan->price_monthly * 12;
        $annualPrice  = (float) $__plan->price_annual;
        if ($monthlyTotal > 0 && $annualPrice < $monthlyTotal) {
            $maxSavingPct = max($maxSavingPct, (int) round((1 - ($annualPrice / $monthlyTotal)) * 100));
        }
    }
@endphp

<style>
    /* Visual state for the billing-period toggle. The real state lives on
       the radio inputs themselves (checked / aria via native semantics) —
       this is presentation only. */
    .ico-price-toggle .btn-check:checked + label {
        background: var(--ico-primary);
        border-color: var(--ico-primary);
        color: var(--ico-on-primary);
    }
    .pricing-card__figure { font-size: 2.1rem; font-weight: 800; color: var(--ico-heading); line-height: 1; }
    .pricing-card__unit { font-size: .85rem; color: var(--ico-muted); }
</style>

{{-- ---------------------------------------------------------------
     Hero
--------------------------------------------------------------- --}}
<section class="ico-hero" data-ico-hero>
    <div class="container-ico text-center position-relative">
        <span class="ico-hero__label" data-ico-hero-item>Pricing</span>
        <h1 class="ico-hero__title" data-ico-hero-item>Simple pricing that scales with you</h1>
        <p class="ico-hero__lead mb-0" data-ico-hero-item>
            Start free, upgrade when you need more. Every paid plan includes a {{ $refundDays }}-day
            money-back guarantee, and you can cancel at any time.
        </p>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Plans
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Choose your plan</h2>
                <p>All prices in {{ $currency }}. Switch to annual billing to save.</p>
            </div>
        </div>

        <div class="d-flex justify-content-center mb-4">
            <div class="btn-group ico-price-toggle" role="group" aria-label="Choose a billing period">
                <input type="radio" class="btn-check" name="pricingPeriod" id="period-monthly" autocomplete="off" checked>
                <label class="btn btn-ico-outline" for="period-monthly">Monthly</label>

                <input type="radio" class="btn-check" name="pricingPeriod" id="period-annual" autocomplete="off">
                <label class="btn btn-ico-outline" for="period-annual">
                    Annual
                    @if ($maxSavingPct > 0)
                        <span class="badge-free ms-1">Save up to {{ $maxSavingPct }}%</span>
                    @endif
                </label>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
            @foreach ($plans as $plan)
                @php
                    $isEnterpriseContact = (float) $plan->price_monthly === 0.0 && $plan->slug === 'enterprise';
                    $monthlyTotal = (float) $plan->price_monthly * 12;
                    $annualPrice  = (float) $plan->price_annual;
                    $savingPct = ($monthlyTotal > 0 && $annualPrice < $monthlyTotal)
                        ? (int) round((1 - ($annualPrice / $monthlyTotal)) * 100)
                        : null;
                @endphp
                <div class="col">
                    <div class="ico-card pricing-card h-100 p-4 d-flex flex-column"
                         @if ($plan->is_featured) style="border:2px solid var(--ico-primary); box-shadow:0 0 0 4px rgba(60,247,162,.15)" @endif>

                        @if ($plan->is_featured)
                            <span class="badge-free mb-2 align-self-start">Most popular</span>
                        @endif

                        <h3 class="h5 mb-1">{{ $plan->name }}</h3>
                        <p class="text-muted small mb-3">{{ $plan->description }}</p>

                        <div class="pricing-card__price mb-3">
                            <div data-price="monthly">
                                <div class="pricing-card__amount">
                                    <span class="pricing-card__figure">{{ number_format((float) $plan->price_monthly, 2) }}</span>
                                    <span class="pricing-card__unit">{{ $currency }} / mo</span>
                                </div>
                                <p class="small text-muted mb-0">Billed monthly</p>
                            </div>
                            <div data-price="annual" hidden>
                                <div class="pricing-card__amount">
                                    <span class="pricing-card__figure">{{ number_format($annualPrice / 12, 2) }}</span>
                                    <span class="pricing-card__unit">{{ $currency }} / mo</span>
                                </div>
                                <p class="small text-muted mb-0">
                                    billed annually ({{ $currency }} {{ number_format($annualPrice, 2) }}/yr)
                                    @if ($savingPct)
                                        <span class="fw-semibold" style="color:var(--ico-primary-darker)">Save {{ $savingPct }}%</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if ($plan->trial_days > 0)
                            <p class="small mb-2" style="color:var(--ico-primary-darker)">
                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>{{ $plan->trial_days }}-day free trial
                            </p>
                        @endif

                        <p class="small text-muted mb-3">
                            @if (is_null($plan->download_limit_daily))
                                <i class="bi bi-infinity me-1" aria-hidden="true"></i>Unlimited downloads
                            @elseif ((int) $plan->download_limit_daily === 0)
                                <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>No downloads
                            @else
                                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $plan->download_limit_daily }} {{ Str::plural('download', (int) $plan->download_limit_daily) }} a day
                            @endif
                        </p>

                        @if ($isEnterpriseContact)
                            <a class="btn btn-ico-outline w-100 mb-3" href="mailto:{{ $settings->get('support_email') }}">
                                Contact sales
                            </a>
                        @else
                            <a class="btn {{ $plan->is_featured ? 'btn-ico-primary' : 'btn-ico-outline' }} w-100 mb-3"
                               href="{{ route('register') }}">
                                {{ (float) $plan->price_monthly <= 0 ? 'Get started free' : 'Choose '.$plan->name }}
                            </a>
                        @endif

                        <ul class="list-unstyled d-grid gap-2 mt-auto mb-0">
                            @foreach ($plan->features as $feature)
                                <li class="d-flex align-items-start gap-2 small @if ($feature->is_highlighted) fw-bold @endif">
                                    @if ($feature->is_included)
                                        <i class="bi bi-check-lg flex-shrink-0" style="color:var(--ico-success)" aria-hidden="true"></i>
                                        <span class="visually-hidden">Included:</span>
                                    @else
                                        <i class="bi bi-x-lg flex-shrink-0 text-muted" aria-hidden="true"></i>
                                        <span class="visually-hidden">Not included:</span>
                                    @endif
                                    <span>{{ $feature->label }}@if ($feature->value) — {{ $feature->value }}@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Licence comparison
--------------------------------------------------------------- --}}
@if ($licenses->isNotEmpty())
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Compare licences</h2>
                <p>Every asset is downloaded under one of these licences.</p>
            </div>
            <a class="section-head__link" href="{{ route('page', 'license') }}">
                Full licence details <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
            @foreach ($licenses as $license)
                <div class="col">
                    <div class="ico-card p-4 h-100">
                        <h3 class="h6 mb-1">{{ $license->name }}</h3>
                        <p class="text-muted small mb-3">{{ $license->summary }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($license->allows_commercial)
                                <span class="badge-free">Commercial use</span>
                            @endif
                            @if ($license->allows_modification)
                                <span class="badge-free">Modification allowed</span>
                            @endif
                            @if ($license->requires_attribution)
                                <span class="badge-premium">Attribution required</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ---------------------------------------------------------------
     FAQ
--------------------------------------------------------------- --}}
<section class="section--tight" data-ico-reveal>
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>Frequently asked questions</h2>
                <p>Straight answers about billing, licensing and refunds.</p>
            </div>
        </div>

        <div class="accordion" id="pricingFaqAccordion" style="max-width:820px">
            @foreach ($faqs as $i => $faq)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="faq-heading-{{ $i }}">
                        <button class="accordion-button @if ($i > 0) collapsed @endif" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq-collapse-{{ $i }}"
                                aria-expanded="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-controls="faq-collapse-{{ $i }}">
                            {{ $faq['q'] }}
                        </button>
                    </h3>
                    <div id="faq-collapse-{{ $i }}" class="accordion-collapse collapse @if ($i === 0) show @endif"
                         aria-labelledby="faq-heading-{{ $i }}" data-bs-parent="#pricingFaqAccordion">
                        <div class="accordion-body text-muted">
                            {{ $faq['a'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Final CTA
--------------------------------------------------------------- --}}
<section class="section" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-cta">
            <h2>Ready to get started?</h2>
            <p>Create a free account today — no card required, upgrade whenever you like.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary" href="{{ route('register') }}">Create free account</a>
                <a class="btn btn-light" href="{{ route('assets.index') }}">Browse assets</a>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
(function () {
    var monthlyRadio = document.getElementById('period-monthly');
    var annualRadio  = document.getElementById('period-annual');
    var monthlyEls   = document.querySelectorAll('[data-price="monthly"]');
    var annualEls    = document.querySelectorAll('[data-price="annual"]');

    function apply(period) {
        monthlyEls.forEach(function (el) { el.hidden = period !== 'monthly'; });
        annualEls.forEach(function (el) { el.hidden = period !== 'annual'; });
    }

    if (monthlyRadio && annualRadio) {
        monthlyRadio.addEventListener('change', function () { if (this.checked) apply('monthly'); });
        annualRadio.addEventListener('change', function () { if (this.checked) apply('annual'); });
    }
})();
</script>
@endpush
