@props(['items', 'reverse' => false])

{{--
    Infinite scrolling text ticker (brief §7).

    The item list is rendered TWICE and the track animates to -50%. At the
    halfway point the second copy sits exactly where the first began, so the
    loop is seamless instead of snapping back to the start.

    These are real links rather than decoration, so the strip is not
    aria-hidden — it is a genuine navigation aid. It pauses on hover so a
    label that catches the eye can actually be clicked.
--}}
@php
    $items = collect($items)->values()->all();
    $count = count($items);
@endphp

@if ($count)
    <div class="marquee {{ $reverse ? 'marquee--reverse' : '' }}">
        <div class="marquee__track">
            @foreach (array_merge($items, $items) as $index => $item)
                <a class="marquee__pill" href="{{ $item['url'] }}"
                   {{-- The second copy duplicates the first, so it is hidden
                        from assistive tech and removed from the tab order —
                        otherwise every label is announced twice. --}}
                   @if ($index >= $count) aria-hidden="true" tabindex="-1" @endif>
                    @if (! empty($item['icon']))
                        <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                    @endif
                    <span>{{ $item['label'] }}</span>
                    @if (! empty($item['count']))
                        <span class="marquee__count">{{ number_format($item['count']) }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endif
