@extends('layouts.admin')

@section('title', 'Dashboard')
@section('subtitle', 'Everything that needs your attention, in one place.')

@section('content')

{{-- ----------------------------------------------------------------
     Action queues first.
     Revenue charts are what dashboards usually lead with, but the thing
     an operator actually needs on opening this page is "what is waiting
     on me?" — so that goes at the top.
---------------------------------------------------------------- --}}
<h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Needs attention</h2>
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">
    @foreach ([
        ['label' => 'Assets to review',  'count' => $queues['assetsAwaitingReview'], 'icon' => 'inbox',          'route' => 'admin.assets.review', 'can' => 'assets.approve'],
        ['label' => 'Applications',      'count' => $queues['applications'],         'icon' => 'person-plus',    'route' => 'admin.applications',  'can' => 'contributors.view'],
        ['label' => 'Payout requests',   'count' => $queues['payoutRequests'],       'icon' => 'cash-stack',     'route' => null, 'can' => null],
        ['label' => 'Open tickets',      'count' => $queues['openTickets'],          'icon' => 'life-preserver', 'route' => null, 'can' => null],
        ['label' => 'Reports',           'count' => $queues['openReports'],          'icon' => 'flag',           'route' => null, 'can' => null],
        ['label' => 'Copyright claims',  'count' => $queues['copyrightClaims'],      'icon' => 'shield-exclamation', 'route' => null, 'can' => null],
    ] as $q)
        @continue($q['can'] && ! auth()->user()->can($q['can']))
        <div class="col">
            @php
                $tag = $q['route'] ? 'a' : 'div';
            @endphp
            <{{ $tag }} class="ico-card queue-card text-decoration-none {{ $q['count'] > 0 ? 'has-work' : '' }}"
               @if($q['route']) href="{{ route($q['route']) }}" @endif>
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-{{ $q['icon'] }}"></i></span>
                <span>
                    <span class="queue-card__count d-block">{{ number_format($q['count']) }}</span>
                    <span class="queue-card__label">{{ $q['label'] }}</span>
                </span>
            </{{ $tag }}>
        </div>
    @endforeach
</div>

{{-- ----------------------------------------------------------------
     Headline numbers
---------------------------------------------------------------- --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                Gross revenue
            </span>
            <span class="stat-card__value">${{ number_format($revenue['gross'], 2) }}</span>
            <span class="stat-card__hint">
                @if (! $revenue['live'])
                    {{-- Honest empty state. A "$0.00" with no explanation looks
                         like a reporting bug rather than a feature not yet built. --}}
                    Checkout is not built yet — no orders exist.
                @else
                    {{ number_format($revenue['orders']) }} paid orders ·
                    ${{ number_format($revenue['refunded'], 2) }} refunded
                @endif
            </span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-collection"></i></span>
                Published assets
            </span>
            <span class="stat-card__value">{{ number_format($catalogue['published']) }}</span>
            <span class="stat-card__hint">
                {{ number_format($catalogue['total']) }} total ·
                {{ number_format($catalogue['packs']) }} packs
            </span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-download"></i></span>
                Downloads
            </span>
            <span class="stat-card__value">{{ number_format($catalogue['downloads']) }}</span>
            <span class="stat-card__hint">All time</span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                Users
            </span>
            <span class="stat-card__value">{{ number_format($people['users']) }}</span>
            <span class="stat-card__hint">
                +{{ number_format($people['newThisWeek']) }} this week ·
                {{ number_format($people['contributors']) }} contributors
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- ------------------------------------------------------------
         Configuration health
    ------------------------------------------------------------ --}}
    <div class="col-lg-5">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-1">Configuration health</h2>
            <p class="small mb-2" style="color:var(--ico-muted)">
                Things that will fail in production if left as they are.
            </p>

            @foreach ($health['checks'] as $check)
                <div class="health-item">
                    <span class="health-item__dot {{ $check['ok'] ? 'is-ok' : 'is-warn' }}"
                          aria-hidden="true"></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="health-item__label">
                            {{ $check['label'] }}
                            <span class="visually-hidden">{{ $check['ok'] ? '— OK' : '— needs attention' }}</span>
                        </div>
                        <div class="health-item__detail">{{ $check['detail'] }}</div>
                    </div>
                    @if ($check['route'] && ! $check['ok'])
                        <a class="btn btn-ico-outline btn-sm flex-shrink-0"
                           href="{{ route($check['route']) }}">Fix</a>
                    @endif
                </div>
            @endforeach

            <div class="d-flex justify-content-between small pt-3" style="color:var(--ico-muted)">
                <span>Storage used</span>
                <span>
                    {{ number_format(($health['storage']['private'] + $health['storage']['public']) / 1048576, 1) }} MB
                </span>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------
         Top assets
    ------------------------------------------------------------ --}}
    <div class="col-lg-7">
        <div class="ico-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h6 mb-0">Most downloaded</h2>
                <a class="small" href="{{ route('assets.index', ['sort' => 'downloads']) }}">View all</a>
            </div>

            @if ($topAssets->isEmpty())
                <p class="small mb-0" style="color:var(--ico-muted)">No published assets yet.</p>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Asset</th>
                                <th scope="col">Contributor</th>
                                <th scope="col" class="text-end">Views</th>
                                <th scope="col" class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topAssets as $asset)
                                <tr>
                                    <td>
                                        <a href="{{ route('assets.show', $asset->slug) }}">{{ $asset->title }}</a>
                                        @if ($asset->is_premium)
                                            <span class="badge-premium ms-1">Premium</span>
                                        @endif
                                    </td>
                                    <td style="color:var(--ico-muted)">{{ $asset->contributor?->display_name ?? '—' }}</td>
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
</div>

<div class="row g-3 mb-4">
    {{-- ------------------------------------------------------------
         Search insight
    ------------------------------------------------------------ --}}
    <div class="col-lg-4">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Top searches</h2>
            @if ($topSearches->isEmpty())
                <p class="small mb-0" style="color:var(--ico-muted)">No searches recorded yet.</p>
            @else
                <ul class="list-unstyled mb-0 small" role="list">
                    @foreach ($topSearches as $s)
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-truncate">{{ $s->query }}</span>
                            <span style="color:var(--ico-muted)">{{ $s->searches }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-1">Searches with no results</h2>
            {{-- These are literal demand the catalogue failed to meet, which
                 makes them the highest-signal input to what to commission next. --}}
            <p class="small mb-2" style="color:var(--ico-muted)">
                Demand you are not yet meeting.
            </p>
            @if ($zeroResults->isEmpty())
                <p class="small mb-0" style="color:var(--ico-muted)">None — every search found something.</p>
            @else
                <ul class="list-unstyled mb-0 small" role="list">
                    @foreach ($zeroResults as $s)
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-truncate">{{ $s->query }}</span>
                            <span style="color:var(--ico-error)">{{ $s->searches }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Top categories</h2>
            @if ($topCategories->isEmpty())
                <p class="small mb-0" style="color:var(--ico-muted)">No categorised assets yet.</p>
            @else
                <ul class="list-unstyled mb-0 small" role="list">
                    @foreach ($topCategories as $c)
                        <li class="d-flex justify-content-between py-1">
                            <a class="text-truncate" href="{{ route('assets.index', ['category' => $c->slug]) }}">{{ $c->name }}</a>
                            <span style="color:var(--ico-muted)">{{ number_format($c->assets_count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

{{-- ----------------------------------------------------------------
     Audit trail
---------------------------------------------------------------- --}}
@can('audit_logs.view')
<div class="ico-card p-3">
    <h2 class="h6 mb-1">Recent admin activity</h2>
    <p class="small mb-2" style="color:var(--ico-muted)">
        Append-only. These records cannot be edited or deleted from the dashboard.
    </p>

    @if ($recentAudit->isEmpty())
        <p class="small mb-0" style="color:var(--ico-muted)">No activity recorded yet.</p>
    @else
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Action</th>
                        <th scope="col">Who</th>
                        <th scope="col">Target</th>
                        <th scope="col">When</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentAudit as $log)
                        <tr>
                            <td><code style="color:var(--ico-primary-darker)">{{ $log->action }}</code></td>
                            <td>{{ $log->actor_name ?? $log->actor_label ?? 'System' }}</td>
                            <td style="color:var(--ico-muted)">
                                {{ class_basename($log->target_type ?? '—') }}
                                @if ($log->target_id) #{{ $log->target_id }} @endif
                            </td>
                            <td style="color:var(--ico-muted)">
                                {{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endcan

@endsection
