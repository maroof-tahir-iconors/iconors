@extends('layouts.admin')

@section('title', 'General settings')
@section('subtitle', 'Site identity, contact details and global defaults.')

@section('content')

    <form method="POST" action="{{ route('admin.settings.general.update') }}" novalidate>
        @csrf
        <input type="hidden" name="_fields" value="registration_enabled,contributor_applications_open,maintenance_mode">

        {{-- Identity --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Identity</h2>

            <div class="mb-3">
                <label for="site_name" class="form-label small fw-semibold">Site name</label>
                <input id="site_name" name="site_name" type="text" maxlength="80" required
                       class="form-control @error('site_name') is-invalid @enderror"
                       value="{{ old('site_name', $settings['site_name']['value'] ?? 'Iconors') }}">
                @error('site_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="site_tagline" class="form-label small fw-semibold">Tagline</label>
                <input id="site_tagline" name="site_tagline" type="text" maxlength="160" required
                       class="form-control @error('site_tagline') is-invalid @enderror"
                       value="{{ old('site_tagline', $settings['site_tagline']['value'] ?? '') }}">
                @error('site_tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="site_description" class="form-label small fw-semibold">Description</label>
                <textarea id="site_description" name="site_description" rows="3" maxlength="300" required
                          class="form-control @error('site_description') is-invalid @enderror"
                          aria-describedby="site_description-help">{{ old('site_description', $settings['site_description']['value'] ?? '') }}</textarea>
                <div id="site_description-help" class="form-text small d-flex justify-content-between">
                    <span>Used as the default meta description. Keep under 300 characters.</span>
                    <span><span id="site_description-count">0</span>/300</span>
                </div>
                @error('site_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Contact --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Contact</h2>

            <div class="mb-3">
                <label for="admin_email" class="form-label small fw-semibold">Admin email</label>
                <input id="admin_email" name="admin_email" type="email" maxlength="255" required
                       class="form-control @error('admin_email') is-invalid @enderror"
                       value="{{ old('admin_email', $settings['admin_email']['value'] ?? '') }}">
                @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="support_email" class="form-label small fw-semibold">Support email</label>
                <input id="support_email" name="support_email" type="email" maxlength="255" required
                       class="form-control @error('support_email') is-invalid @enderror"
                       value="{{ old('support_email', $settings['support_email']['value'] ?? '') }}"
                       aria-describedby="support_email-help">
                <div id="support_email-help" class="form-text small">
                    Shown publicly in the footer and help pages.
                </div>
                @error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Localisation --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Localisation</h2>

            <div class="row g-3 mb-1">
                <div class="col-md-3">
                    <label for="default_currency" class="form-label small fw-semibold">Default currency</label>
                    <input id="default_currency" name="default_currency" type="text" maxlength="3" required
                           style="text-transform:uppercase"
                           class="form-control @error('default_currency') is-invalid @enderror"
                           value="{{ old('default_currency', $settings['default_currency']['value'] ?? 'USD') }}">
                    @error('default_currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="default_locale" class="form-label small fw-semibold">Default locale</label>
                    @php
                        $currentLocale = old('default_locale', $settings['default_locale']['value'] ?? 'en');
                    @endphp
                    <select id="default_locale" name="default_locale" required
                            class="form-select @error('default_locale') is-invalid @enderror">
                        @foreach ([
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'pt' => 'Portuguese',
                            'ar' => 'Arabic',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($currentLocale === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('default_locale')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label for="timezone" class="form-label small fw-semibold">Timezone</label>
                    @php
                        $currentTimezone = old('timezone', $settings['timezone']['value'] ?? 'UTC');
                    @endphp
                    <select id="timezone" name="timezone" required
                            class="form-select @error('timezone') is-invalid @enderror">
                        @foreach ([
                            'UTC', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid',
                            'Europe/Moscow', 'America/New_York', 'America/Chicago', 'America/Denver',
                            'America/Los_Angeles', 'America/Sao_Paulo', 'America/Toronto', 'Asia/Dubai',
                            'Asia/Karachi', 'Asia/Kolkata', 'Asia/Dhaka', 'Asia/Bangkok', 'Asia/Singapore',
                            'Asia/Hong_Kong', 'Asia/Tokyo', 'Asia/Seoul', 'Asia/Shanghai', 'Australia/Sydney',
                            'Africa/Lagos', 'Africa/Johannesburg',
                        ] as $tz)
                            <option value="{{ $tz }}" @selected($currentTimezone === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Access --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Access</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="registration_enabled" name="registration_enabled" value="1"
                           @checked(old('registration_enabled', $settings['registration_enabled']['value'] ?? true))
                           aria-describedby="registration_enabled-help">
                    <label class="form-check-label small fw-semibold" for="registration_enabled">Registration enabled</label>
                </div>
                <div id="registration_enabled-help" class="form-text small">
                    When off, no new accounts can be created.
                </div>
                @error('registration_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="contributor_applications_open" name="contributor_applications_open" value="1"
                           @checked(old('contributor_applications_open', $settings['contributor_applications_open']['value'] ?? true))
                           aria-describedby="contributor_applications_open-help">
                    <label class="form-check-label small fw-semibold" for="contributor_applications_open">Contributor applications open</label>
                </div>
                <div id="contributor_applications_open-help" class="form-text small">
                    When off, the become-a-contributor form is closed.
                </div>
                @error('contributor_applications_open')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="maintenance_mode" name="maintenance_mode" value="1"
                           @checked(old('maintenance_mode', $settings['maintenance_mode']['value'] ?? false))
                           aria-describedby="maintenance_mode-help">
                    <label class="form-check-label small fw-semibold text-warning" for="maintenance_mode">Maintenance mode</label>
                </div>
                <div id="maintenance_mode-help" class="form-text small">
                    Hides the public site from visitors. You will still be able to reach the admin area.
                </div>
                @error('maintenance_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save general settings</button>
    </form>

@endsection

@push('scripts')
    <script>
        (function () {
            var textarea = document.getElementById('site_description');
            var count = document.getElementById('site_description-count');
            if (!textarea || !count) return;

            function update() {
                count.textContent = textarea.value.length;
            }

            textarea.addEventListener('input', update);
            update();
        })();
    </script>
@endpush
