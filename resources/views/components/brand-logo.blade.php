@props(['variant' => 'main', 'showName' => true])

@inject('settings', 'App\Services\Settings\SettingsService')

@php
    // Resolve the uploaded logo for this variant, falling back to the main
    // upload, then to the built-in mark. A missing dark logo should degrade
    // to the main one rather than leaving a gap in the header.
    $key  = 'logo_'.$variant;
    $path = $settings->get($key) ?: $settings->get('logo_main');
    $name = $settings->get('site_name', 'Iconors');
@endphp

<a {{ $attributes->merge(['class' => 'ico-logo', 'href' => route('home')]) }}
   aria-label="{{ $name }} home">
    @if ($path)
        {{-- Uploaded logo. Height-constrained so any aspect ratio fits the
             header without pushing its height around. --}}
        <img src="{{ Storage::disk('public')->url($path) }}"
             alt="{{ $name }}"
             style="height:34px; width:auto; max-width:180px; object-fit:contain">
    @else
        <span class="ico-logo__mark" aria-hidden="true">
            <svg width="19" height="19" viewBox="0 0 28 28" fill="none">
                <path d="M5.4 5.3h3.6v3.6H5.4zM23.4 12h-3.3c-.2-1.7-1.5-3-3.3-3.2h-3.6V5.3h5.2c2.2 0 4 1.8 4 4zM23.4 15.4v3.8c0 2.1-1.8 3.6-3.9 3.6H10c-2.2 0-3.9-1.7-3.9-3.8V12h3.5v3.3c0 2.2 1.7 3.8 3.9 3.8h2.6c2.1 0 3.6-1.4 3.8-3.4z"
                      fill="currentColor"/>
            </svg>
        </span>
        @if ($showName)
            <span>{{ $name }}</span>
        @endif
    @endif
</a>
