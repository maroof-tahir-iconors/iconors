@extends('layouts.contributor')

@section('title', 'Analytics')
@section('subtitle', 'How your work is performing.')

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

    $maxTypeDownloads     = $byType->max('downloads') ?: 0;
    $maxCategoryDownloads = $byCategory->max('downloads') ?: 0;
@endphp

@section('content')

{{-- ----------------------------------------------------------------
     Stat tiles
---------------------------------------------------------------- --}}
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
<h2 class="h6 text-uppercase mb-2" style="letter-spacing:.07em; color:var(--ico-muted)">Downloads, last 30 days</h2>
<div class="ico-card p-3 mb-4">
    @include('contributor.partials.downloads-chart', ['chart' => $chart])
</div>

{{-- ----------------------------------------------------------------
     By type / by category
---------------------------------------------------------------- --}}
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">By asset type</h2>
            @if ($byType->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></div>
                    <p class="small mb-0" style="color:var(--ico-muted)">No published assets yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col" class="text-end">Assets</th>
                                <th scope="col" class="text-end">Views</th>
                                <th scope="col" class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byType as $row)
                                @php
                                    $share = $maxTypeDownloads > 0 ? round($row->downloads / $maxTypeDownloads * 100) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        {{ Str::headline($row->type) }}
                                        <div style="height:4px; border-radius:2px; background:var(--ico-border); margin-top:.35rem; max-width:120px;">
                                            <div style="height:100%; width:{{ $share }}%; background:var(--ico-primary); border-radius:2px;"></div>
                                        </div>
                                        <span class="visually-hidden">{{ $share }}% of the top type's downloads</span>
                                    </td>
                                    <td class="text-end">{{ number_format($row->assets) }}</td>
                                    <td class="text-end">{{ number_format($row->views) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($row->downloads) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-2">By category</h2>
            @if ($byCategory->isEmpty())
                <div class="empty-state">
                    <div class="empty-state__icon"><i class="bi bi-tags" aria-hidden="true"></i></div>
                    <p class="small mb-0" style="color:var(--ico-muted)">No categorised assets yet.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Category</th>
                                <th scope="col" class="text-end">Assets</th>
                                <th scope="col" class="text-end">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byCategory as $row)
                                @php
                                    $share = $maxCategoryDownloads > 0 ? round($row->downloads / $maxCategoryDownloads * 100) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        {{ $row->category }}
                                        <div style="height:4px; border-radius:2px; background:var(--ico-border); margin-top:.35rem; max-width:120px;">
                                            <div style="height:100%; width:{{ $share }}%; background:var(--ico-primary); border-radius:2px;"></div>
                                        </div>
                                        <span class="visually-hidden">{{ $share }}% of the top category's downloads</span>
                                    </td>
                                    <td class="text-end">{{ number_format($row->assets) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($row->downloads) }}</td>
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
     Top assets
---------------------------------------------------------------- --}}
<div class="ico-card p-3">
    <h2 class="h6 mb-2">Top assets</h2>
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
                        <th scope="col" class="text-end">Likes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topAssets as $asset)
                        <tr>
                            <td><a href="{{ route('assets.show', $asset->slug) }}">{{ $asset->title }}</a></td>
                            <td>
                                <span class="badge {{ $statusColors[$asset->status] ?? 'bg-secondary' }}">
                                    {{ $statusLabels[$asset->status] ?? Str::headline($asset->status) }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($asset->views_count) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($asset->downloads_count) }}</td>
                            <td class="text-end">{{ number_format($asset->likes_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
