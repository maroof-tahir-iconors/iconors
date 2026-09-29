@extends('layouts.contributor')

@section('title', 'My assets')
@section('subtitle', 'Manage your submissions and published work.')

@php
    $statusTabs = [
        'submitted'         => 'Submitted',
        'under_review'      => 'Under review',
        'changes_requested' => 'Changes requested',
        'published'         => 'Published',
        'rejected'          => 'Rejected',
        'draft'             => 'Draft',
    ];

    $statusColors = [
        'submitted'         => 'bg-secondary',
        'under_review'      => 'bg-info text-dark',
        'changes_requested' => 'bg-warning text-dark',
        'published'         => 'bg-success',
        'rejected'          => 'bg-danger',
        'draft'             => 'bg-secondary',
    ];
@endphp

@section('content')

    <div class="d-flex flex-wrap align-items-end justify-content-end gap-3 mb-4">
        <a class="btn btn-ico-primary" href="{{ route('contributor.assets.create') }}">
            <i class="bi bi-cloud-upload me-1" aria-hidden="true"></i> Upload asset
        </a>
    </div>

    <nav class="mb-4" aria-label="Filter assets by status">
        <ul class="nav nav-pills flex-wrap gap-1">
            <li class="nav-item">
                <a class="nav-link {{ request('status') ? '' : 'active' }}"
                   href="{{ route('contributor.assets.index') }}">
                    All
                </a>
            </li>
            @foreach ($statusTabs as $value => $label)
                <li class="nav-item">
                    <a class="nav-link {{ request('status') === $value ? 'active' : '' }}"
                       href="{{ route('contributor.assets.index', ['status' => $value]) }}">
                        {{ $label }} <span class="badge bg-secondary bg-opacity-50 ms-1">{{ $counts[$value] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($assets->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-images" aria-hidden="true"></i></div>
            <h2 class="h5">No assets here yet</h2>
            <p class="text-muted small mb-3">
                @if (request('status'))
                    No assets with this status.
                @else
                    Upload your first asset to get started.
                @endif
            </p>
            <a class="btn btn-ico-primary btn-sm" href="{{ route('contributor.assets.create') }}">Upload asset</a>
        </div>
    @else
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
            @foreach ($assets as $asset)
                <div class="col">
                    <article class="ico-card h-100">
                        <div class="asset-tile__media">
                            @php
                                $preview = $asset->previews->first();
                            @endphp
                            @if ($preview)
                                <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                                     alt="{{ $asset->title }}"
                                     loading="lazy" decoding="async"
                                     width="{{ $preview->width ?: 240 }}" height="{{ $preview->height ?: 240 }}">
                            @else
                                <div class="d-grid place-items-center text-muted" aria-hidden="true">
                                    <i class="bi bi-image fs-1 opacity-25"></i>
                                </div>
                            @endif
                        </div>
                        <div class="asset-tile__body">
                            <h3 class="asset-tile__title">{{ $asset->title }}</h3>
                            <p class="asset-tile__meta mb-2">
                                <span class="badge {{ $statusColors[$asset->status] ?? 'bg-secondary' }}">
                                    {{ $statusTabs[$asset->status] ?? Str::headline($asset->status) }}
                                </span>
                            </p>

                            @if ($asset->status === 'changes_requested' && $asset->status_reason)
                                <div class="alert alert-warning small py-2 px-2 mb-0" role="alert">
                                    {{ $asset->status_reason }}
                                </div>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $assets->links() }}
        </div>
    @endif

@endsection
