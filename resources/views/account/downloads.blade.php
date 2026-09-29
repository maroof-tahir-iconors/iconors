@extends('layouts.app')

@section('title', 'My Downloads — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    @if ($downloads->isEmpty())
        <div class="ico-card empty-state">
            <div class="empty-state__icon"><i class="bi bi-download" aria-hidden="true"></i></div>
            <h2 class="h6">No downloads yet</h2>
            <p class="text-muted small mb-3">Assets you download will be listed here, with the licence you got them under.</p>
            <a href="{{ route('assets.index') }}" class="btn btn-ico-primary btn-sm">Browse assets</a>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Asset</th>
                            <th scope="col">Type</th>
                            <th scope="col">Access</th>
                            <th scope="col">Downloaded</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($downloads as $download)
                            @php
                                $asset = $download->asset;
                                $preview = $asset?->previews->firstWhere('is_primary', true)
                                    ?? $asset?->previews->first();
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="download-thumb" aria-hidden="true">
                                            @if ($preview)
                                                <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                                                     alt="" loading="lazy" decoding="async" width="40" height="40">
                                            @else
                                                <i class="bi bi-image"></i>
                                            @endif
                                        </span>
                                        <span>{{ $asset?->title ?? 'Deleted asset' }}</span>
                                    </div>
                                </td>
                                <td>{{ $asset?->assetType?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge text-bg-secondary">
                                        {{ \Illuminate\Support\Str::headline($download->access_type) }}
                                    </span>
                                </td>
                                <td>
                                    <span title="{{ $download->created_at->toDayDateTimeString() }}">
                                        {{ $download->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($asset)
                                        <a href="{{ route('assets.show', $asset->slug) }}"
                                           class="btn btn-ico-outline btn-sm">View</a>
                                    @else
                                        <span class="text-muted small">Unavailable</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($downloads->hasPages())
            <div class="mt-4">{{ $downloads->links() }}</div>
        @endif
    @endif

</div>
@endsection
