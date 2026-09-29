@extends('layouts.app')

@section('title', 'Invite Members — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ico-card p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                    <div>
                        <h2 class="h6 mb-1">Invite members</h2>
                        <p class="text-muted small mb-0">
                            Share one subscription and one licence across your team.
                        </p>
                    </div>
                </div>

                @if ($seats > 1)
                    {{-- Plan grants seats, but the team workspace is not built yet.
                         Better to say so than to show an invite box that silently
                         does nothing. --}}
                    <div class="api-notice">
                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <div>
                            <strong>Your {{ $plan?->name }} plan includes {{ $seats }} seats.</strong>
                            <p class="mb-0 small text-muted">
                                Team management is still in development — invitations cannot be
                                sent yet. Your seats are reserved and will be available here
                                when it ships.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="api-notice">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <div>
                            <strong>Team seats are not included in your {{ $plan?->name ?? 'Free' }} plan.</strong>
                            <p class="mb-0 small text-muted">
                                Upgrade to a team plan to invite colleagues and share one licence.
                            </p>
                        </div>
                    </div>

                    @if ($teamPlans->isNotEmpty())
                        <hr class="my-4">
                        <h3 class="h6 mb-2">Plans with team seats</h3>
                        <ul class="list-unstyled d-grid gap-2 mb-3" role="list">
                            @foreach ($teamPlans as $teamPlan)
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span>
                                        <strong>{{ $teamPlan->name }}</strong>
                                        <span class="text-muted small">
                                            — {{ $teamPlan->team_seats }} seats
                                            @if ($teamPlan->price_monthly > 0)
                                                · {{ $teamPlan->currency }} {{ number_format($teamPlan->price_monthly, 2) }}/mo
                                            @endif
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a href="{{ route('pricing') }}" class="btn btn-ico-primary btn-sm">See team plans</a>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ico-card p-4 h-100">
                <h2 class="h6 mb-2">Your plan</h2>
                <p class="account-plan-name mb-1">{{ $plan?->name ?? 'Free' }}</p>
                <p class="text-muted small mb-3">
                    {{ trans_choice(':count seat|:count seats', $seats, ['count' => $seats]) }}
                </p>
                <a href="{{ route('account.subscription') }}" class="btn btn-ico-outline btn-sm w-100">
                    Manage subscription
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
