@extends('layouts.contributor')

@section('title', 'Profile')
@section('subtitle', 'How you appear on the marketplace.')

@php
    $statusLabels = [
        'pending'            => 'Pending',
        'under_review'       => 'Under review',
        'more_info_required' => 'More info required',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
        'suspended'          => 'Suspended',
    ];

    $statusColors = [
        'pending'            => 'bg-secondary',
        'under_review'       => 'bg-info text-dark',
        'more_info_required' => 'bg-warning text-dark',
        'approved'           => 'bg-success',
        'rejected'           => 'bg-danger',
        'suspended'          => 'bg-danger',
    ];

    $countries = [
        'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
        'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
        'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands',
        'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
        'FI' => 'Finland', 'IE' => 'Ireland', 'PT' => 'Portugal',
        'PL' => 'Poland', 'BR' => 'Brazil', 'MX' => 'Mexico',
        'IN' => 'India', 'JP' => 'Japan', 'SG' => 'Singapore',
        'AE' => 'United Arab Emirates', 'ZA' => 'South Africa', 'NZ' => 'New Zealand',
        'PK' => 'Pakistan',
    ];

    $currentBio = old('biography', $contributor->biography);
@endphp

@section('content')

<div class="row g-3">
    {{-- ------------------------------------------------------------
         Read-only account status
    ------------------------------------------------------------ --}}
    <div class="col-lg-4">
        <div class="ico-card p-3 h-100">
            <h2 class="h6 mb-3">Status</h2>
            <p class="mb-2">
                <span class="badge {{ $statusColors[$contributor->status] ?? 'bg-secondary' }}">
                    {{ $statusLabels[$contributor->status] ?? Str::headline($contributor->status) }}
                </span>
                @if ($contributor->is_verified)
                    <span class="badge bg-success ms-1">
                        <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Verified
                    </span>
                @endif
            </p>
            <p class="small mb-2" style="color:var(--ico-muted)">
                Member since {{ $contributor->created_at->format('F Y') }}
            </p>
            <a class="small d-inline-block mb-3" href="{{ route('assets.index', ['contributor' => $contributor->slug]) }}">
                View your public page <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
            </a>
            <p class="small mb-0" style="color:var(--ico-muted)">
                Your status, commission rate and verification are managed by the Iconors team.
            </p>
        </div>
    </div>

    {{-- ------------------------------------------------------------
         Editable public profile
    ------------------------------------------------------------ --}}
    <div class="col-lg-8">
        <div class="ico-card p-3">
            <h2 class="h6 mb-3">Public profile</h2>

            <form method="POST" action="{{ route('contributor.profile.update') }}">
                @csrf

                <div class="mb-3">
                    <label for="display_name" class="form-label">Display name</label>
                    <input type="text" class="form-control" id="display_name" name="display_name"
                           value="{{ old('display_name', $contributor->display_name) }}"
                           maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label for="biography" class="form-label d-flex justify-content-between align-items-baseline">
                        <span>Biography</span>
                        <span class="small" style="color:var(--ico-muted)">
                            <span id="bio-count">{{ Str::length($currentBio ?? '') }}</span>/2000
                        </span>
                    </label>
                    <textarea class="form-control" id="biography" name="biography" rows="5"
                              maxlength="2000">{{ $currentBio }}</textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="country" class="form-label">Country</label>
                        <select class="form-select" id="country" name="country">
                            <option value="">Select a country</option>
                            @foreach ($countries as $code => $name)
                                <option value="{{ $code }}" @selected(old('country', $contributor->country) === $code)>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="website" class="form-label">Website</label>
                        <input type="url" class="form-control" id="website" name="website"
                               value="{{ old('website', $contributor->website) }}"
                               placeholder="https://example.com">
                    </div>
                    <div class="col-md-6">
                        <label for="portfolio_url" class="form-label">Portfolio URL</label>
                        <input type="url" class="form-control" id="portfolio_url" name="portfolio_url"
                               value="{{ old('portfolio_url', $contributor->portfolio_url) }}"
                               placeholder="https://dribbble.com/you">
                    </div>
                </div>

                <button type="submit" class="btn btn-ico-primary">Save changes</button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    var bio = document.getElementById('biography');
    var count = document.getElementById('bio-count');
    if (!bio || !count) return;
    bio.addEventListener('input', function () {
        count.textContent = bio.value.length;
    });
})();
</script>
@endpush
