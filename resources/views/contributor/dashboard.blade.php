@extends('layouts.contributor')

@section('title', 'Overview')
@section('subtitle', 'Your assets, audience and earnings.')

@php
    $statusLabels = [
        'draft'             => 'Draft',
        'submitted'         => 'Submitted',
        'under_review'      => 'Under review',
        'changes_requested' => 'Changes requested',
        'published'         => 'Published',
        'rejected'          => 'Rejected',
    ];

    $statusColors = [
        'draft'             => 'bg-secondary',
        'submitted'         => 'bg-secondary',
        'under_review'      => 'bg-info text-dark',
        'changes_requested' => 'bg-warning text-dark',
        'published'         => 'bg-success',
        'rejected'          => 'bg-danger',
    ];

    $showNeedsAction = $needsAction['changes_requested']->isNotEmpty()
        || $needsAction['drafts'] > 0
        || $needsAction['inReview'] > 0;
@endphp

@section('content')

{{-- ----------------------------------------------------------------
     Needs attention — only rendered when there is something to act on.
---------------------------------------------------------------- --}}
@if ($showNeedsAction)
    <h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Needs attention</h2>
    <div class="row g-2 mb-4">
        @foreach ($needsAction['changes_requested'] as $asset)
            <div class="col-12">
                <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-start gap-2 mb-0" role="alert">
                    <div>
                        <strong>{{ $asset->title }}</strong> needs changes before it can be resubmitted.
                        @if ($asset->status_reason)
                            <p class="small mb-0 mt-1">{{ $asset->status_reason }}</p>
                        @endif
                    </div>
                    <a class="btn btn-ico-outline btn-sm flex-shrink-0"
                       href="{{ route('contributor.assets.index', ['status' => 'changes_requested']) }}">
                        Review
                    </a>
                </div>
            </div>
        @endforeach

        @if ($needsAction['drafts'] > 0 || $needsAction['inReview'] > 0)
            <div class="col-12">
                <div class="d-flex flex-wrap gap-3 small" style="color:var(--ico-muted)">
                    @if ($needsAction['drafts'] > 0)
                        <span>
                            <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>
                            {{ $needsAction['drafts'] }} draft{{ $needsAction['drafts'] === 1 ? '' : 's' }} not yet submitted
                        </span>
                    @endif
                    @if ($needsAction['inReview'] > 0)
                        <span>
                            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                            {{ $needsAction['inReview'] }} awaiting review
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif

{{-- ----------------------------------------------------------------
     Earnings
---------------------------------------------------------------- --}}
<h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Earnings</h2>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                Available
            </span>
            <span class="stat-card__value">{{ $earnings['currency'] }} {{ number_format($earnings['available'], 2) }}</span>
            <span class="stat-card__hint">Ready to withdraw</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                Pending
            </span>
            <span class="stat-card__value">{{ $earnings['currency'] }} {{ number_format($earnings['pending'], 2) }}</span>
            <span class="stat-card__hint">In the {{ $earnings['holdingDays'] }}-day holding period</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
                Paid out
            </span>
            <span class="stat-card__value">{{ $earnings['currency'] }} {{ number_format($earnings['paid'], 2) }}</span>
            <span class="stat-card__hint">All time</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                Lifetime
            </span>
            <span class="stat-card__value">{{ $earnings['currency'] }} {{ number_format($earnings['lifetime'], 2) }}</span>
            <span class="stat-card__hint">Pending + available + paid</span>
        </div>
    </div>
</div>

@if ($earnings['lifetime'] == 0)
    <div class="alert alert-secondary small mb-3" role="alert">
        No earnings yet. Earnings accrue when a subscriber downloads your work or someone buys it —
        checkout is not live yet, so these will stay at zero for now.
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="ico-card p-3 h-100">
            <h3 class="h6 mb-3">Your commission</h3>
            <div class="row row-cols-3 g-2 mb-3 text-center">
                <div class="col">
                    <div class="fw-bold fs-5">{{ number_format($earnings['rates']['one_time'], 1) }}%</div>
                    <div class="small" style="color:var(--ico-muted)">One-time sales</div>
                </div>
                <div class="col">
                    <div class="fw-bold fs-5">{{ number_format($earnings['rates']['subscription'], 1) }}%</div>
                    <div class="small" style="color:var(--ico-muted)">Subscription downloads</div>
                </div>
                <div class="col">
                    <div class="fw-bold fs-5">{{ number_format($earnings['rates']['api'], 1) }}%</div>
                    <div class="small" style="color:var(--ico-muted)">API downloads</div>
                </div>
            </div>
            <p class="small mb-0" style="color:var(--ico-muted)">
                Earnings become withdrawable after {{ $earnings['holdingDays'] }} days, which protects
                against payouts on sales that are later refunded.
            </p>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ico-card p-3 h-100 d-flex flex-column">
            <h3 class="h6 mb-2">Request payout</h3>
            <p class="small mb-3" style="color:var(--ico-muted)">
                Minimum payout is {{ $earnings['currency'] }} {{ number_format($earnings['minimum'], 2) }}.
            </p>
            <form method="POST" action="{{ route('contributor.payout.request') }}" class="mt-auto">
                @csrf
                <button type="submit" class="btn btn-ico-primary w-100" @disabled(! $earnings['canWithdraw'])>
                    Request payout
                </button>
                @unless ($earnings['canWithdraw'])
                    <p class="small mt-2 mb-0" style="color:var(--ico-muted)">
                        You need {{ number_format($earnings['shortfall'], 2) }} more to reach the
                        {{ number_format($earnings['minimum'], 2) }} minimum.
                    </p>
                @endunless
            </form>
        </div>
    </div>
</div>

{{-- ----------------------------------------------------------------
     Performance
---------------------------------------------------------------- --}}
<h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Performance</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-collection"></i></span>
                Published assets
            </span>
            <span class="stat-card__value">{{ number_format($stats['assets']) }}</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-eye"></i></span>
                Total views
            </span>
            <span class="stat-card__value">{{ number_format($stats['views']) }}</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-download"></i></span>
                Total downloads
            </span>
            <span class="stat-card__value">{{ number_format($stats['downloads']) }}</span>
            <span class="stat-card__hint">{{ number_format($stats['last30']) }} downloads in the last 30 days</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-percent"></i></span>
                Conversion
            </span>
            <span class="stat-card__value">{{ number_format($stats['conversion'], 1) }}%</span>
            <span class="stat-card__hint">downloads per 100 views</span>
        </div>
    </div>
</div>

{{-- ----------------------------------------------------------------
     Downloads chart
---------------------------------------------------------------- --}}
<h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Downloads, last 14 days</h2>
<div class="ico-card p-3 mb-4">
    @include('contributor.partials.downloads-chart', ['chart' => $chart])
</div>

{{-- ----------------------------------------------------------------
     Top assets / recent downloads
---------------------------------------------------------------- --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Top performing assets</h2>
            @if ($topAssets->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="bi bi-collection" aria-hidden="true"></i></div>
                    <p class="small mb-0" style="color:var(--ico-muted)">No published assets yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Asset</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Views</th>
                                <th scope="col" class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topAssets as $asset)
                                <tr>
                                    <td>
                                        <a href="{{ route('assets.show', $asset->slug) }}">{{ $asset->title }}</a>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusColors[$asset->status] ?? 'bg-secondary' }}">
                                            {{ $statusLabels[$asset->status] ?? Str::headline($asset->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ number_format($asset->views_count) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($asset->downloads_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Recent downloads</h2>
            @if ($recent->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="bi bi-download" aria-hidden="true"></i></div>
                    <p class="small mb-0" style="color:var(--ico-muted)">No downloads recorded yet.</p>
                </div>
            @else
                <ul class="list-unstyled mb-0" role="list">
                    @foreach ($recent as $row)
                        <li class="d-flex justify-content-between align-items-center py-2"
                            style="border-bottom:1px solid var(--ico-border)">
                            <span class="text-truncate me-2">{{ $row->title }}</span>
                            <span class="d-flex align-items-center gap-2 flex-shrink-0">
                                <span class="badge bg-secondary bg-opacity-50">{{ Str::headline($row->access_type) }}</span>
                                <span class="small" style="color:var(--ico-muted)">
                                    {{ \Carbon\Carbon::parse($row->created_at)->diffForHumans() }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

{{-- ----------------------------------------------------------------
     Asset status breakdown
---------------------------------------------------------------- --}}
<h2 class="h6 mb-2">Asset status breakdown</h2>
<div class="d-flex flex-wrap gap-2">
    @forelse ($statusCounts as $status => $count)
        <a class="badge {{ $statusColors[$status] ?? 'bg-secondary' }} text-decoration-none"
           href="{{ route('contributor.assets.index', ['status' => $status]) }}">
            {{ $statusLabels[$status] ?? Str::headline($status) }} &middot; {{ $count }}
        </a>
    @empty
        <p class="small mb-0" style="color:var(--ico-muted)">No assets yet.</p>
    @endforelse
</div>

@endsection
