@extends('layouts.admin')

@section('title', 'Asset review')
@section('subtitle', 'Review contributor submissions before they go live.')

@php
    $statusTabs = [
        'submitted'         => 'Submitted',
        'under_review'      => 'Under review',
        'changes_requested' => 'Changes requested',
        'published'         => 'Published',
        'rejected'          => 'Rejected',
    ];

    $currentStatus = request('status', 'submitted');
@endphp

@section('content')

    <nav class="mb-4" aria-label="Filter submissions by status">
        <ul class="nav nav-pills flex-wrap gap-1">
            @foreach ($statusTabs as $value => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $currentStatus === $value ? 'active' : '' }}"
                       href="{{ route('admin.assets.review', ['status' => $value]) }}">
                        {{ $label }} <span class="badge bg-secondary bg-opacity-50 ms-1">{{ $counts[$value] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($assets->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <h2 class="h6">Nothing awaiting review</h2>
            <p class="text-muted small mb-0">There are no submissions with this status.</p>
        </div>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            @foreach ($assets as $asset)
                <div class="col">
                    <article class="ico-card h-100 d-flex flex-column">
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

                            <span class="position-absolute bottom-0 start-0 m-2">
                                @if ($asset->is_premium)
                                    <span class="badge-premium">Premium &middot; ${{ number_format((float) $asset->price, 2) }}</span>
                                @else
                                    <span class="badge-free">Free</span>
                                @endif
                            </span>
                        </div>

                        <div class="asset-tile__body flex-grow-1 d-flex flex-column">
                            <h3 class="asset-tile__title">{{ $asset->title }}</h3>
                            <p class="asset-tile__meta mb-3">
                                {{ $asset->contributor?->display_name ?? 'Unknown contributor' }}
                                &middot; {{ $asset->assetType?->name ?? 'Unknown type' }}
                            </p>

                            <div class="mt-auto d-flex flex-wrap gap-1">
                                <form method="POST" action="{{ route('admin.assets.approve', $asset) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ico-primary btn-sm">Approve &amp; publish</button>
                                </form>

                                <button type="button" class="btn btn-ico-outline btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#changes-asset-{{ $asset->id }}">
                                    Request changes
                                </button>

                                <button type="button" class="btn btn-ico-outline btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#reject-asset-{{ $asset->id }}">
                                    Reject
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $assets->links() }}
        </div>

        {{-- Request-changes / reject modals, one pair per asset. --}}
        @foreach ($assets as $asset)
            <div class="modal fade" id="changes-asset-{{ $asset->id }}" tabindex="-1"
                 aria-labelledby="changes-asset-{{ $asset->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.assets.changes', $asset) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="changes-asset-{{ $asset->id }}-label">
                                    Request changes to &ldquo;{{ $asset->title }}&rdquo;
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="changes-feedback-{{ $asset->id }}" class="form-label small fw-semibold">Feedback</label>
                                <textarea id="changes-feedback-{{ $asset->id }}" name="feedback" rows="4"
                                          class="form-control" required minlength="10"
                                          aria-describedby="changes-feedback-{{ $asset->id }}-help"></textarea>
                                <div id="changes-feedback-{{ $asset->id }}-help" class="form-text small">
                                    Shown to the contributor. At least 10 characters.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-ico-primary btn-sm">Request changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="reject-asset-{{ $asset->id }}" tabindex="-1"
                 aria-labelledby="reject-asset-{{ $asset->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.assets.reject', $asset) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="reject-asset-{{ $asset->id }}-label">
                                    Reject &ldquo;{{ $asset->title }}&rdquo;
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="reject-reason-{{ $asset->id }}" class="form-label small fw-semibold">Reason</label>
                                <textarea id="reject-reason-{{ $asset->id }}" name="reason" rows="4"
                                          class="form-control" required minlength="10"
                                          aria-describedby="reject-reason-{{ $asset->id }}-help"></textarea>
                                <div id="reject-reason-{{ $asset->id }}-help" class="form-text small">
                                    Shown to the contributor. At least 10 characters.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
