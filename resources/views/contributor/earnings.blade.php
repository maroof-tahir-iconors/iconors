@extends('layouts.contributor')

@section('title', 'Earnings')
@section('subtitle', 'What you have earned and how to withdraw it.')

@php
    $earningStatusLabels = [
        'pending'   => 'Pending',
        'available' => 'Available',
        'paid'      => 'Paid',
        'reversed'  => 'Reversed',
        'on_hold'   => 'On hold',
    ];

    $earningStatusColors = [
        'pending'   => 'bg-secondary',
        'available' => 'bg-success',
        'paid'      => 'bg-info text-dark',
        'reversed'  => 'bg-danger',
        'on_hold'   => 'bg-warning text-dark',
    ];

    $payoutStatusLabels = [
        'requested'    => 'Requested',
        'under_review' => 'Under review',
        'approved'     => 'Approved',
        'processing'   => 'Processing',
        'completed'    => 'Completed',
        'rejected'     => 'Rejected',
        'failed'       => 'Failed',
        'cancelled'    => 'Cancelled',
    ];

    $payoutStatusColors = [
        'requested'    => 'bg-secondary',
        'under_review' => 'bg-info text-dark',
        'approved'     => 'bg-info text-dark',
        'processing'   => 'bg-info text-dark',
        'completed'    => 'bg-success',
        'rejected'     => 'bg-danger',
        'failed'       => 'bg-danger',
        'cancelled'    => 'bg-secondary',
    ];
@endphp

@section('content')

{{-- ----------------------------------------------------------------
     Balance tiles
---------------------------------------------------------------- --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                Available
            </span>
            <span class="stat-card__value">{{ $summary['currency'] }} {{ number_format($summary['available'], 2) }}</span>
            <span class="stat-card__hint">Ready to withdraw</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                Pending
            </span>
            <span class="stat-card__value">{{ $summary['currency'] }} {{ number_format($summary['pending'], 2) }}</span>
            <span class="stat-card__hint">In the {{ $summary['holdingDays'] }}-day holding period</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
                Paid out
            </span>
            <span class="stat-card__value">{{ $summary['currency'] }} {{ number_format($summary['paid'], 2) }}</span>
            <span class="stat-card__hint">All time</span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="ico-card stat-card">
            <span class="stat-card__label">
                <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                Lifetime
            </span>
            <span class="stat-card__value">{{ $summary['currency'] }} {{ number_format($summary['lifetime'], 2) }}</span>
            <span class="stat-card__hint">Pending + available + paid</span>
        </div>
    </div>
</div>

@if ($summary['lifetime'] == 0)
    <div class="alert alert-secondary small mb-3" role="alert">
        No earnings yet. Earnings accrue when a subscriber downloads your work or someone buys it —
        checkout is not live yet, so these will stay at zero for now.
    </div>
@endif

{{-- ----------------------------------------------------------------
     Request payout
---------------------------------------------------------------- --}}
<div class="ico-card p-3 mb-4">
    <h2 class="h6 mb-2">Request payout</h2>
    <p class="small mb-3" style="color:var(--ico-muted)">
        Minimum payout is {{ $summary['currency'] }} {{ number_format($summary['minimum'], 2) }}.
        Earnings become withdrawable after {{ $summary['holdingDays'] }} days, which protects against
        payouts on sales that are later refunded.
    </p>
    <form method="POST" action="{{ route('contributor.payout.request') }}">
        @csrf
        <button type="submit" class="btn btn-ico-primary" @disabled(! $summary['canWithdraw'])>
            Request payout
        </button>
        @unless ($summary['canWithdraw'])
            <p class="small mt-2 mb-0" style="color:var(--ico-muted)">
                You need {{ number_format($summary['shortfall'], 2) }} more to reach the
                {{ number_format($summary['minimum'], 2) }} minimum.
            </p>
        @endunless
    </form>
</div>

<div class="row g-3 mb-4">
    {{-- ------------------------------------------------------------
         Earnings by source
    ------------------------------------------------------------ --}}
    <div class="col-lg-5">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Earnings by source</h2>
            @if ($summary['bySource']->isEmpty())
                <p class="small mb-0" style="color:var(--ico-muted)">No earnings recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Source</th>
                                <th scope="col" class="text-end">Entries</th>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['bySource'] as $row)
                                <tr>
                                    <td>{{ Str::headline($row->source) }}</td>
                                    <td class="text-end">{{ number_format($row->entries) }}</td>
                                    <td class="text-end fw-semibold">
                                        {{ $summary['currency'] }} {{ number_format($row->total, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ------------------------------------------------------------
         Payout history
    ------------------------------------------------------------ --}}
    <div class="col-lg-7">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">Payout history</h2>
            @if ($payouts->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="bi bi-cash-stack" aria-hidden="true"></i></div>
                    <p class="small mb-0" style="color:var(--ico-muted)">No payouts requested yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col" class="text-end">Amount</th>
                                <th scope="col" class="text-end">Fee</th>
                                <th scope="col" class="text-end">Net</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payouts as $payout)
                                <tr>
                                    <td><code style="color:var(--ico-primary-darker)">{{ $payout->reference }}</code></td>
                                    <td class="text-end">{{ $payout->currency }} {{ number_format($payout->amount, 2) }}</td>
                                    <td class="text-end">{{ $payout->currency }} {{ number_format($payout->fee, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ $payout->currency }} {{ number_format($payout->net_amount, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $payoutStatusColors[$payout->status] ?? 'bg-secondary' }}">
                                            {{ $payoutStatusLabels[$payout->status] ?? Str::headline($payout->status) }}
                                        </span>
                                    </td>
                                    <td style="color:var(--ico-muted)">
                                        {{ \Carbon\Carbon::parse($payout->created_at)->format('j M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ----------------------------------------------------------------
     Earnings ledger
---------------------------------------------------------------- --}}
<div class="ico-card p-3">
    <h2 class="h6 mb-1">Earnings ledger</h2>
    <p class="small mb-3" style="color:var(--ico-muted)">
        The commission rate is frozen on each row at the moment it was earned — changing the global
        rate never alters past earnings.
    </p>

    @if ($entries->isEmpty())
        <div class="empty-state">
            <div class="empty-state__icon"><i class="bi bi-receipt" aria-hidden="true"></i></div>
            <h3 class="h6">No earnings yet</h3>
            <p class="small mb-0" style="color:var(--ico-muted)">
                Earnings accrue when a subscriber downloads your work or someone buys it — checkout is
                not live yet, so this ledger is empty for now.
            </p>
        </div>
    @else
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Asset</th>
                        <th scope="col">Source</th>
                        <th scope="col" class="text-end">Gross</th>
                        <th scope="col" class="text-end">Rate</th>
                        <th scope="col" class="text-end">Net</th>
                        <th scope="col">Status</th>
                        <th scope="col">Available on</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td style="color:var(--ico-muted)">
                                {{ \Carbon\Carbon::parse($entry->created_at)->format('j M Y') }}
                            </td>
                            <td>
                                @if ($entry->asset_slug)
                                    <a href="{{ route('assets.show', $entry->asset_slug) }}">{{ $entry->asset_title }}</a>
                                @else
                                    <span style="color:var(--ico-muted)">{{ $entry->asset_title ?? '—' }}</span>
                                @endif
                            </td>
                            <td>{{ Str::headline($entry->source) }}</td>
                            <td class="text-end">{{ number_format($entry->gross_amount, 2) }}</td>
                            <td class="text-end">{{ number_format($entry->commission_rate, 1) }}%</td>
                            <td class="text-end fw-semibold">{{ number_format($entry->net_amount, 2) }}</td>
                            <td>
                                <span class="badge {{ $earningStatusColors[$entry->status] ?? 'bg-secondary' }}">
                                    {{ $earningStatusLabels[$entry->status] ?? Str::headline($entry->status) }}
                                </span>
                            </td>
                            <td style="color:var(--ico-muted)">
                                {{ $entry->available_at ? \Carbon\Carbon::parse($entry->available_at)->format('j M Y') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $entries->links() }}
        </div>
    @endif
</div>

@endsection
