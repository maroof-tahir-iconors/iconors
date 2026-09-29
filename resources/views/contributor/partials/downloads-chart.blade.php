{{--
    Pure-CSS bar chart of downloads over time.

    Expects `$chart`: an array of ['date' => ..., 'label' => ..., 'value' => int].

    Bar height is colour-and-shape only by default, which fails WCAG for
    anyone using a screen reader — so every bar also carries a visually
    hidden "{label}: {value}" text equivalent, not just a hover title.
--}}
@php
    $maxValue = collect($chart)->max('value') ?? 0;
    $safeMax  = max(1, $maxValue);
    $labelEvery = max(1, (int) ceil(count($chart) / 8));
@endphp

@if ($maxValue == 0)
    <p class="small mb-0" style="color:var(--ico-muted)">No downloads recorded in this period.</p>
@else
    <div class="d-flex align-items-end gap-1" style="height:160px" role="img"
         aria-label="Bar chart of downloads across {{ count($chart) }} days">
        @foreach ($chart as $i => $point)
            @php
                $barPct = $point['value'] > 0 ? max(3, round($point['value'] / $safeMax * 100)) : 1;
            @endphp
            <div class="d-flex flex-column align-items-center justify-content-end h-100 flex-fill" style="min-width:4px;">
                <div title="{{ $point['label'] }}: {{ $point['value'] }}"
                     style="width:100%; height:{{ $barPct }}%; background:var(--ico-primary); border-radius:3px 3px 0 0;">
                    <span class="visually-hidden">{{ $point['label'] }}: {{ $point['value'] }} downloads</span>
                </div>
                <span class="small mt-1 text-truncate" aria-hidden="true"
                      style="color:var(--ico-muted); font-size:.65rem; max-width:100%; {{ $i % $labelEvery !== 0 ? 'visibility:hidden;' : '' }}">
                    {{ $point['label'] }}
                </span>
            </div>
        @endforeach
    </div>
@endif
