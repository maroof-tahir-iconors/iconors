@extends('layouts.app')

@section('title', 'API — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ico-card p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-code-slash"></i></span>
                    <div>
                        <h2 class="h6 mb-1">Iconors API</h2>
                        <p class="text-muted small mb-0">
                            Search the catalogue and pull assets straight into your product.
                        </p>
                    </div>
                </div>

                {{-- No key generator here on purpose: the API itself is not built
                     yet, so issuing keys would hand out credentials that nothing
                     accepts. The plan gate below is real — `plans.api_access`. --}}
                <div class="api-notice">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <div>
                        <strong>API access is coming soon.</strong>
                        <p class="mb-0 small text-muted">
                            Endpoints and key management are still in development. When they ship,
                            @if ($hasApiAccess)
                                your <strong>{{ $plan?->name }}</strong> plan already includes API access —
                                your keys will appear on this page.
                            @else
                                you will be able to generate and revoke keys here.
                            @endif
                        </p>
                    </div>
                </div>

                @unless ($hasApiAccess)
                    <hr class="my-4">
                    <h3 class="h6 mb-2">Plans that will include the API</h3>

                    @if ($apiPlans->isEmpty())
                        <p class="text-muted small mb-0">
                            No plan currently lists API access. An administrator can enable it
                            per plan under Admin → Plans.
                        </p>
                    @else
                        <ul class="list-unstyled d-grid gap-2 mb-3" role="list">
                            @foreach ($apiPlans as $apiPlan)
                                <li class="d-flex align-items-center justify-content-between gap-2">
                                    <span>
                                        <strong>{{ $apiPlan->name }}</strong>
                                        <span class="text-muted small">
                                            @if ($apiPlan->price_monthly > 0)
                                                — {{ $apiPlan->currency }} {{ number_format($apiPlan->price_monthly, 2) }}/mo
                                            @endif
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('pricing') }}" class="btn btn-ico-primary btn-sm">Compare plans</a>
                    @endif
                @endunless
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ico-card p-4 h-100">
                <h2 class="h6 mb-2">Your plan</h2>
                <p class="account-plan-name mb-1">{{ $plan?->name ?? 'Free' }}</p>
                <p class="text-muted small mb-3">
                    API access:
                    @if ($hasApiAccess)
                        <span class="badge text-bg-success">Included</span>
                    @else
                        <span class="badge text-bg-secondary">Not included</span>
                    @endif
                </p>
                <a href="{{ route('account.subscription') }}" class="btn btn-ico-outline btn-sm w-100">
                    Manage subscription
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
