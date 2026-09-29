@extends('layouts.app')

@section('title', 'Subscription — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="ico-card p-4 h-100">
                <h2 class="h6 mb-2">Current plan</h2>
                <p class="account-plan-name mb-1">{{ $plan?->name ?? 'Free' }}</p>

                @if ($plan && $plan->price_monthly > 0)
                    <p class="text-muted small mb-3">
                        {{ $plan->currency }} {{ number_format($plan->price_monthly, 2) }} / month
                    </p>
                @else
                    <p class="text-muted small mb-3">No charge — free forever.</p>
                @endif

                @if ($plan?->description)
                    <p class="small mb-3">{{ $plan->description }}</p>
                @endif

                <a href="{{ route('pricing') }}" class="btn btn-ico-primary btn-sm">
                    {{ $plan && $plan->price_monthly > 0 ? 'Change plan' : 'Upgrade' }}
                </a>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="ico-card p-4 h-100">
                <h2 class="h6 mb-3">Usage</h2>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card">
                            <span class="stat-card__label">
                                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-download"></i></span>
                                Downloads today
                            </span>
                            <span class="stat-card__value">
                                {{ number_format($downloadsToday) }}@if ($plan?->download_limit_daily)<span class="account-limit"> / {{ number_format($plan->download_limit_daily) }}</span>@endif
                            </span>
                            <span class="stat-card__hint">
                                @if ($plan?->download_limit_daily)
                                    Resets at midnight.
                                @else
                                    No daily limit on this plan.
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="stat-card">
                            <span class="stat-card__label">
                                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                                Team seats
                            </span>
                            <span class="stat-card__value">{{ number_format($plan?->team_seats ?? 1) }}</span>
                            <span class="stat-card__hint">
                                <a href="{{ route('account.members') }}">Manage members</a>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Billing history needs a live gateway. Saying so beats an
                     empty table that looks like lost invoices. --}}
                <hr class="my-4">
                <h3 class="h6 mb-2">Billing history</h3>
                <p class="text-muted small mb-0">
                    No invoices yet. Billing history appears here once a payment gateway is
                    connected and your first payment is taken.
                </p>
            </div>
        </div>
    </div>

    <h2 class="h6 mb-3">All plans</h2>
    <div class="row g-3">
        @foreach ($plans as $option)
            <div class="col-12 col-md-6 col-xl-3">
                <article class="ico-card plan-card @if($plan && $option->id === $plan->id) is-current @endif">
                    <div class="plan-card__head">
                        <h3 class="plan-card__name">{{ $option->name }}</h3>
                        @if ($plan && $option->id === $plan->id)
                            <span class="badge text-bg-success">Current</span>
                        @elseif ($option->is_featured)
                            <span class="badge text-bg-secondary">Popular</span>
                        @endif
                    </div>

                    <p class="plan-card__price">
                        @if ($option->price_monthly > 0)
                            <span>{{ $option->currency }} {{ number_format($option->price_monthly, 2) }}</span>
                            <span class="plan-card__per">/mo</span>
                        @elseif ($option->slug === 'enterprise')
                            <span>Custom</span>
                        @else
                            <span>Free</span>
                        @endif
                    </p>

                    @if ($option->features->isNotEmpty())
                        <ul class="plan-card__features" role="list">
                            @foreach ($option->features->take(4) as $feature)
                                <li>
                                    <i class="bi {{ $feature->is_included ? 'bi-check-lg' : 'bi-x-lg' }}"
                                       aria-hidden="true"></i>
                                    {{ $feature->label }}@if ($feature->value) <span class="text-muted">{{ $feature->value }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! $plan || $option->id !== $plan->id)
                        <a href="{{ route('pricing') }}" class="btn btn-ico-outline btn-sm w-100">Choose</a>
                    @endif
                </article>
            </div>
        @endforeach
    </div>

</div>
@endsection
