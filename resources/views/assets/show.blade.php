@extends('layouts.app')

@section('title', $asset->title.' — Iconors')
@section('meta_description', Str::limit(strip_tags($asset->description ?? $asset->title), 155))

@push('meta')
    {{-- Structured data (brief §29). Only facts we actually hold are
         emitted — no invented ratings, per the brief's prohibition on
         misleading schema.

         NOTE: built in a raw PHP block, not the json directive, whose
         parser mis-detects the closing bracket of a multi-line array.
         Also: never name a raw-PHP or json directive inside a Blade
         comment — raw blocks are extracted before comments are stripped,
         so the comment loses its terminator and eats the rest of the
         template. That is what broke this file the first time. --}}
    @php
        $schema = array_filter([
            '@context'    => 'https://schema.org',
            '@type'       => 'CreativeWork',
            'name'        => $asset->title,
            'description' => Str::limit(strip_tags($asset->description ?? ''), 300),
            'url'         => route('assets.show', $asset->slug),
            'dateCreated' => optional($asset->published_at)->toIso8601String(),
            'creator'     => $asset->contributor
                ? ['@type' => 'Person', 'name' => $asset->contributor->display_name]
                : null,
            'license'     => $asset->license?->name,
        ]);
    @endphp
    <script type="application/ld+json">
        {!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
<div class="container-ico px-3 py-4">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('assets.index') }}">Assets</a></li>
            @if ($asset->category)
                <li class="breadcrumb-item">
                    <a href="{{ route('assets.index', ['category' => $asset->category->slug]) }}">
                        {{ $asset->category->name }}
                    </a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $asset->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ico-card p-4 p-md-5 d-grid place-items-center"
                 style="min-height: 420px; background: var(--ico-surface)">
                @php
                    $preview = $asset->previews->firstWhere('is_primary', true) ?? $asset->previews->first();
                @endphp

                @if ($preview)
                    <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                         alt="{{ $asset->title }}"
                         style="max-width:100%;max-height:420px"
                         width="{{ $preview->width ?: 420 }}" height="{{ $preview->height ?: 420 }}">
                @else
                    <div class="text-muted text-center" aria-hidden="true">
                        <i class="bi bi-image display-1 opacity-25"></i>
                    </div>
                @endif
            </div>

            @if ($asset->description)
                <div class="ico-card p-4 mt-3">
                    <h2 class="h6 mb-2">About this asset</h2>
                    <p class="mb-0 text-muted small">{{ $asset->description }}</p>
                </div>
            @endif

            @if ($asset->tags->isNotEmpty())
                <div class="ico-card p-4 mt-3">
                    <h2 class="h6 mb-3">Tags</h2>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($asset->tags as $tag)
                            <a class="btn btn-ico-outline btn-sm"
                               href="{{ route('assets.index', ['q' => $tag->name]) }}">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="col-lg-4">
            <div class="ico-card p-4 position-sticky" style="top: 88px">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <h1 class="h4 mb-0">{{ $asset->title }}</h1>
                    @if ($asset->is_premium)
                        <span class="badge-premium">Premium</span>
                    @else
                        <span class="badge-free">Free</span>
                    @endif
                </div>

                @if ($asset->contributor)
                    <p class="small text-muted mb-3">
                        by
                        <a href="{{ route('assets.index', ['contributor' => $asset->contributor->slug]) }}">
                            {{ $asset->contributor->display_name }}
                        </a>
                    </p>
                @endif

                @if ($asset->is_premium && $asset->price > 0)
                    <div class="h3 mb-3">${{ number_format((float) $asset->price, 2) }}</div>
                @endif

                <div class="d-grid gap-2 mb-3">
                    @auth
                        {{-- Posts to the entitlement endpoint, which returns a
                             short-lived signed URL. The page never contains a
                             working download link, so scraping it yields nothing. --}}
                        <button class="btn btn-ico-primary" type="button"
                                data-ico-download="{{ $asset->id }}">
                            <i class="bi bi-download me-1" aria-hidden="true"></i> Download
                        </button>
                    @else
                        <a class="btn btn-ico-primary" href="{{ route('login') }}">
                            <i class="bi bi-download me-1" aria-hidden="true"></i> Sign in to download
                        </a>
                    @endauth

                    <button class="btn btn-ico-outline" type="button" data-ico-like="{{ $asset->id }}">
                        <i class="bi bi-heart me-1" aria-hidden="true"></i> Like
                    </button>
                </div>

                <div id="ico-download-status" class="small" role="status" aria-live="polite"></div>

                <hr style="border-color: var(--ico-border)">

                <dl class="row small mb-0">
                    @if ($asset->assetType)
                        <dt class="col-5 text-muted fw-normal">Type</dt>
                        <dd class="col-7 mb-2">{{ $asset->assetType->name }}</dd>
                    @endif
                    @if ($asset->style)
                        <dt class="col-5 text-muted fw-normal">Style</dt>
                        <dd class="col-7 mb-2">{{ $asset->style->name }}</dd>
                    @endif
                    @if ($asset->formats->isNotEmpty())
                        <dt class="col-5 text-muted fw-normal">Formats</dt>
                        <dd class="col-7 mb-2">{{ $asset->formats->pluck('name')->join(', ') }}</dd>
                    @endif
                    @if ($asset->license)
                        <dt class="col-5 text-muted fw-normal">Licence</dt>
                        <dd class="col-7 mb-2">{{ $asset->license->name }}</dd>
                    @endif
                    <dt class="col-5 text-muted fw-normal">Downloads</dt>
                    <dd class="col-7 mb-2">{{ number_format($asset->downloads_count) }}</dd>
                    <dt class="col-5 text-muted fw-normal">Updated</dt>
                    <dd class="col-7 mb-0">{{ optional($asset->published_at)->format('j M Y') }}</dd>
                </dl>
            </div>
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-5">
            <h2 class="h4 mb-3">Related assets</h2>
            <div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3">
                @foreach ($related as $item)
                    <div class="col"><x-asset-card :asset="$item" /></div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
/**
 * Download flow: POST for a signed URL, then follow it.
 *
 * Two steps rather than a direct link so the button reflects the real
 * entitlement decision — a premium asset returns a readable reason
 * instead of silently 403-ing a file request.
 */
document.querySelectorAll('[data-ico-download]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.icoDownload;
        const status = document.getElementById('ico-download-status');
        const original = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing…';
        status.textContent = '';

        try {
            const res = await fetch(`/download/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            const data = await res.json();

            if (res.ok && data.url) {
                status.textContent = 'Your download is starting…';
                status.style.color = 'var(--ico-primary-darker)';
                window.location.href = data.url;
            } else {
                status.textContent = data.message || 'This download is not available.';
                status.style.color = 'var(--ico-error)';
            }
        } catch (e) {
            status.textContent = 'Something went wrong. Please try again.';
            status.style.color = 'var(--ico-error)';
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
});
</script>
@endpush
