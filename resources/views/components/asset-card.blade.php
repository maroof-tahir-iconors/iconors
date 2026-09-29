@props(['asset', 'eager' => false])

@php
    $preview = $asset->previews->firstWhere('is_primary', true) ?? $asset->previews->first();
@endphp

{{--
    Asset tile (brief §10).

    Not wrapped in a single <a>: the title carries the accessible name and
    the like/save controls are separate buttons. One outer anchor would make
    them illegal nested interactive content and unreachable by keyboard.
--}}
<article class="ico-card asset-tile">
    <div class="asset-tile__media">
        @php
            $isLottie = $preview && ($preview->type === 'lottie' || str_ends_with($preview->path, '.json'));
        @endphp

        @if ($preview && $isLottie)
            {{-- Lottie JSON cannot render in an <img> — it produced a grid of
                 broken images. The player is lazy-loaded and only plays on
                 hover/focus, so a listing of 36 animations does not run 36
                 render loops at once (brief §4, §10). --}}
            {{-- No play affordance: these autoplay on entering the viewport.
                 The spinner is only a placeholder while the player loads,
                 and is replaced the moment the animation appears. --}}
            <div class="ico-lottie"
                 data-ico-lottie="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                 role="img" aria-label="{{ $asset->title }} animation">
                <span class="ico-lottie__poster" aria-hidden="true">
                    <span class="ico-lottie__spinner"></span>
                </span>
            </div>
        @elseif ($preview)
            <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                 alt="{{ $asset->title }}"
                 {{-- Above-the-fold tiles load eagerly; lazy-loading the first
                      screen is what makes a grid appear empty on arrival. --}}
                 loading="{{ $eager ? 'eager' : 'lazy' }}"
                 @if($eager) fetchpriority="high" @endif
                 decoding="async"
                 width="240" height="240">
        @else
            <div class="text-center" style="color: var(--ico-muted)" aria-hidden="true">
                <i class="bi bi-image" style="font-size:2rem;opacity:.35"></i>
            </div>
        @endif

        <div class="asset-tile__actions">
            <button class="asset-tile__action" type="button"
                    data-ico-like="{{ $asset->id }}"
                    aria-label="Like {{ $asset->title }}">
                <i class="bi bi-heart" aria-hidden="true"></i>
            </button>
            <button class="asset-tile__action" type="button"
                    data-ico-save="{{ $asset->id }}"
                    aria-label="Save {{ $asset->title }} to a collection">
                <i class="bi bi-bookmark" aria-hidden="true"></i>
            </button>
        </div>

        <span class="position-absolute" style="bottom:.55rem; left:.55rem;">
            @if ($asset->is_premium)
                <span class="badge-premium">Premium</span>
            @else
                <span class="badge-free">Free</span>
            @endif
        </span>
    </div>

    <div class="asset-tile__body">
        <h3 class="asset-tile__title">
            <a class="stretched-link text-reset" href="{{ route('assets.show', $asset->slug) }}">
                {{ $asset->title }}
            </a>
        </h3>
        <p class="asset-tile__meta">
            <span class="text-truncate">{{ $asset->contributor?->display_name ?? 'Iconors' }}</span>
            @if ($asset->is_premium && $asset->price > 0)
                <span class="asset-tile__price">${{ number_format((float) $asset->price, 2) }}</span>
            @endif
        </p>
    </div>
</article>
