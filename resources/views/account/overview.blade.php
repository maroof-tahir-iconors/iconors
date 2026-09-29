@extends('layouts.app')

@section('title', 'Overview — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    {{-- Carried over from the old standalone dashboard view. This is the ONLY
         way an administrator stops impersonating, so it must not be dropped
         when this page changes shape. --}}
    @if (session()->has('impersonator_id'))
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4" role="alert">
            <span>
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                You are viewing the site as {{ $user->name }}.
            </span>
            <form method="POST" action="{{ route('impersonate.stop') }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn-dark btn-sm">Stop impersonating</button>
            </form>
        </div>
    @endif

    <div class="account-welcome mb-4">
        <h2 class="account-welcome__title">Welcome back, {{ $user->name }}</h2>
        <p class="account-welcome__sub">Here's what's happening with your account.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-download"></i></span>
                    Downloads
                </span>
                <span class="stat-card__value">{{ number_format($downloadsCount) }}</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-collection"></i></span>
                    Collections
                </span>
                <span class="stat-card__value">{{ number_format($collectionsCount) }}</span>
                <span class="stat-card__hint">
                    <a href="{{ route('account.collections') }}">Manage collections</a>
                </span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-heart"></i></span>
                    Likes
                </span>
                <span class="stat-card__value">{{ number_format($likesCount) }}</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
                    Member since
                </span>
                <span class="stat-card__value">{{ $user->created_at->format('M Y') }}</span>
                <span class="stat-card__hint">{{ $user->currentPlan()?->name ?? 'Free' }} plan</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ico-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 mb-0">Recent downloads</h2>
                    <a class="small" href="{{ route('account.downloads') }}">View all</a>
                </div>

                @if ($recentDownloads->isEmpty())
                    <div class="empty-state">
                        <div class="empty-state__icon"><i class="bi bi-download" aria-hidden="true"></i></div>
                        <h3 class="h6">No downloads yet</h3>
                        <p class="text-muted small mb-3">Browse the catalogue to find your first asset.</p>
                        <a href="{{ route('assets.index') }}" class="btn btn-ico-primary btn-sm">Browse assets</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="admin-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Asset</th>
                                    <th scope="col">Access</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentDownloads as $download)
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
                                                @if ($asset)
                                                    <a href="{{ route('assets.show', $asset->slug) }}">{{ $asset->title }}</a>
                                                @else
                                                    <span>Deleted asset</span>
                                                @endif
                                            </div>
                                        </td>
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ico-card p-4 h-100">
                <h2 class="h6 mb-3">Quick links</h2>

                <div class="d-grid gap-2">
                    <a href="{{ route('assets.index') }}" class="btn btn-ico-outline btn-sm">Browse assets</a>
                    <a href="{{ route('account.subscription') }}" class="btn btn-ico-outline btn-sm">Subscription</a>

                    @if ($user->contributor && $user->contributor->status === 'approved')
                        <a href="{{ route('contributor.dashboard') }}" class="btn btn-ico-primary btn-sm">Contributor studio</a>
                    @else
                        <a href="{{ route('contributor.apply') }}" class="btn btn-ico-primary btn-sm">Become a contributor</a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="d-grid">
                        @csrf
                        <button class="btn btn-ico-outline btn-sm" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
