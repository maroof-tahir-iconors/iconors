@extends('layouts.admin')

@section('title', 'Branding')
@section('subtitle', 'Colours, typography and logos. Changes apply across the whole site immediately.')

@php
    // Normalises a stored/old hex value into a 6-digit lowercase form the
    // native <input type="color"> element will accept. Falls back to black
    // rather than leaving the swatch on an invalid value.
    $normalizeHex = function ($value) {
        if (! $value || ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return '#000000';
        }

        $hex = ltrim($value, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.strtolower($hex);
    };

    $primaryColors = [
        'brand_primary'       => 'Primary',
        'brand_primary_light' => 'Primary (light)',
        'brand_primary_dark'  => 'Primary (dark)',
        'brand_secondary'     => 'Secondary',
        'brand_accent'        => 'Accent',
        'brand_accent_deep'   => 'Accent (deep)',
    ];

    $textColors = [
        'brand_heading_color'    => 'Heading text',
        'brand_text_color'       => 'Body text',
        'brand_muted_color'      => 'Muted text',
        'brand_link_color'       => 'Links',
        'brand_border_color'     => 'Borders',
        'brand_surface_color'    => 'Surface',
        'brand_background_color' => 'Background',
    ];

    $statusColors = [
        'brand_success_color' => 'Success',
        'brand_warning_color' => 'Warning',
        'brand_error_color'   => 'Error',
        'brand_info_color'    => 'Info',
    ];

    $logoFields = [
        'logo_main'          => 'Main logo',
        'logo_light'         => 'Light logo',
        'logo_dark'          => 'Dark logo',
        'logo_mobile'        => 'Mobile logo',
        'logo_email'         => 'Email logo',
        'logo_invoice'       => 'Invoice logo',
        'favicon'            => 'Favicon',
        'social_share_image' => 'Social share image',
    ];
@endphp

@section('content')

    <div class="alert alert-info" role="alert">
        Every colour here is written as a CSS variable at runtime — no rebuild or deploy is needed.
        Leave a logo field empty to keep the current image.
    </div>

    <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" novalidate>
        @csrf

        {{-- A. Brand colours --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Brand colours</h2>

            <div class="alert alert-info small" role="alert">
                The primary colour is used as a button and badge FILL. A readable label colour is calculated
                from it automatically, so you do not need to pick one.
            </div>

            <div class="row g-3">
                @foreach ($primaryColors as $key => $label)
                    @php
                        $value = old($key, $settings[$key]['value'] ?? null);
                    @endphp
                    <div class="col-md-6">
                        <label for="{{ $key }}" class="form-label small fw-semibold">{{ $label }}</label>
                        <div class="color-field">
                            <label for="{{ $key }}_swatch" class="visually-hidden">{{ $label }} colour swatch</label>
                            <input type="color" id="{{ $key }}_swatch"
                                   class="form-control form-control-color flex-grow-0"
                                   value="{{ $normalizeHex($value) }}"
                                   data-color-pair="{{ $key }}">
                            <input id="{{ $key }}" name="{{ $key }}" type="text" maxlength="7"
                                   pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                                   class="form-control @error($key) is-invalid @enderror"
                                   value="{{ $value }}"
                                   aria-describedby="{{ $key }}-help">
                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div id="{{ $key }}-help" class="visually-hidden">Hex colour, e.g. #3CF7A2</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- B. Text & surface colours --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Text & surface colours</h2>

            <div class="row g-3">
                @foreach ($textColors as $key => $label)
                    @php
                        $value = old($key, $settings[$key]['value'] ?? null);
                    @endphp
                    <div class="col-md-6">
                        <label for="{{ $key }}" class="form-label small fw-semibold">{{ $label }}</label>
                        <div class="color-field">
                            <label for="{{ $key }}_swatch" class="visually-hidden">{{ $label }} colour swatch</label>
                            <input type="color" id="{{ $key }}_swatch"
                                   class="form-control form-control-color flex-grow-0"
                                   value="{{ $normalizeHex($value) }}"
                                   data-color-pair="{{ $key }}">
                            <input id="{{ $key }}" name="{{ $key }}" type="text" maxlength="7"
                                   pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                                   class="form-control @error($key) is-invalid @enderror"
                                   value="{{ $value }}">
                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- C. Status colours --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Status colours</h2>

            <div class="row g-3">
                @foreach ($statusColors as $key => $label)
                    @php
                        $value = old($key, $settings[$key]['value'] ?? null);
                    @endphp
                    <div class="col-md-6">
                        <label for="{{ $key }}" class="form-label small fw-semibold">{{ $label }}</label>
                        <div class="color-field">
                            <label for="{{ $key }}_swatch" class="visually-hidden">{{ $label }} colour swatch</label>
                            <input type="color" id="{{ $key }}_swatch"
                                   class="form-control form-control-color flex-grow-0"
                                   value="{{ $normalizeHex($value) }}"
                                   data-color-pair="{{ $key }}">
                            <input id="{{ $key }}" name="{{ $key }}" type="text" maxlength="7"
                                   pattern="^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$"
                                   class="form-control @error($key) is-invalid @enderror"
                                   value="{{ $value }}">
                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- D. Typography & shape --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Typography & shape</h2>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="brand_font_body" class="form-label small fw-semibold">Body font</label>
                    <input id="brand_font_body" name="brand_font_body" type="text" maxlength="120"
                           class="form-control @error('brand_font_body') is-invalid @enderror"
                           value="{{ old('brand_font_body', $settings['brand_font_body']['value'] ?? '') }}">
                    @error('brand_font_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="brand_font_heading" class="form-label small fw-semibold">Heading font</label>
                    <input id="brand_font_heading" name="brand_font_heading" type="text" maxlength="120"
                           class="form-control @error('brand_font_heading') is-invalid @enderror"
                           value="{{ old('brand_font_heading', $settings['brand_font_heading']['value'] ?? '') }}">
                    @error('brand_font_heading')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="brand_radius" class="form-label small fw-semibold">Corner radius</label>
                    <input id="brand_radius" name="brand_radius" type="text"
                           class="form-control @error('brand_radius') is-invalid @enderror"
                           value="{{ old('brand_radius', $settings['brand_radius']['value'] ?? '') }}"
                           aria-describedby="brand_radius-help">
                    <div id="brand_radius-help" class="form-text small">e.g. 14px</div>
                    @error('brand_radius')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="brand_container_width" class="form-label small fw-semibold">Container width</label>
                    <input id="brand_container_width" name="brand_container_width" type="text"
                           class="form-control @error('brand_container_width') is-invalid @enderror"
                           value="{{ old('brand_container_width', $settings['brand_container_width']['value'] ?? '') }}"
                           aria-describedby="brand_container_width-help">
                    <div id="brand_container_width-help" class="form-text small">e.g. 1280px</div>
                    @error('brand_container_width')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-1">
                <label for="brand_gradient" class="form-label small fw-semibold">Gradient</label>
                <input id="brand_gradient" name="brand_gradient" type="text" maxlength="255"
                       class="form-control @error('brand_gradient') is-invalid @enderror"
                       value="{{ old('brand_gradient', $settings['brand_gradient']['value'] ?? '') }}"
                       aria-describedby="brand_gradient-help">
                <div id="brand_gradient-help" class="form-text small">
                    Only a plain linear-gradient of hex stops is accepted, e.g. linear-gradient(-135deg, #1E0046, #1E0064).
                </div>
                @error('brand_gradient')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- E. Logos & images --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Logos & images</h2>

            <div class="row g-3">
                @foreach ($logoFields as $key => $label)
                    @php
                        $current = $settings[$key]['value'] ?? null;
                    @endphp
                    <div class="col-md-6">
                        <label for="{{ $key }}" class="form-label small fw-semibold">{{ $label }}</label>

                        @if ($current)
                            <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded"
                                 style="background-image:linear-gradient(45deg,#80808022 25%,transparent 25%),linear-gradient(-45deg,#80808022 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#80808022 75%),linear-gradient(-45deg,transparent 75%,#80808022 75%);background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0;">
                                <img src="{{ Storage::disk('public')->url($current) }}"
                                     alt="Current {{ strtolower($label) }}"
                                     style="max-height:40px;max-width:160px;object-fit:contain;">
                                <span class="small fw-semibold">Current</span>
                            </div>
                        @endif

                        <input id="{{ $key }}" name="{{ $key }}" type="file"
                               accept=".svg,.png,.jpg,.jpeg,.webp,.ico"
                               class="form-control @error($key) is-invalid @enderror"
                               aria-describedby="{{ $key }}-help">

                        @if ($key === 'logo_light' || $key === 'logo_dark')
                            <div class="form-text small">
                                Light logo = for use ON light backgrounds. Dark logo = for use ON dark backgrounds.
                            </div>
                        @endif

                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>

            <div id="logo-fields-help" class="form-text small mt-2">
                SVG, PNG, JPG, WebP or ICO. Max 2 MB. SVG files are sanitised before they are stored.
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save branding</button>
    </form>

@endsection

@push('scripts')
    <script>
        (function () {
            var hexPattern = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;

            function normalize(hex) {
                hex = hex.replace('#', '');
                if (hex.length === 3) {
                    hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
                }
                return '#' + hex.toLowerCase();
            }

            document.querySelectorAll('[data-color-pair]').forEach(function (swatch) {
                var text = document.getElementById(swatch.getAttribute('data-color-pair'));
                if (!text) return;

                swatch.addEventListener('input', function () {
                    text.value = swatch.value;
                    text.classList.remove('is-invalid');
                });

                text.addEventListener('input', function () {
                    if (hexPattern.test(text.value)) {
                        swatch.value = normalize(text.value);
                    }
                });
            });
        })();
    </script>
@endpush
