@extends('layouts.admin')

@section('title', 'Email settings')
@section('subtitle', 'Configure the mailer used to send transactional email.')

@section('content')

    <div class="alert alert-info" role="alert">
        Credentials are encrypted before they are stored and are never displayed again after saving.
        Leave a field blank to keep its current value.
    </div>

    {{-- Form A: mailer configuration --}}
    <div class="ico-card p-4 p-md-5 mb-4">
        <h2 class="h6 mb-3">Mailer configuration</h2>

        <form method="POST" action="{{ route('admin.settings.mail.update') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="mail_driver" class="form-label small fw-semibold">Driver</label>
                <select id="mail_driver" name="mail_driver"
                        class="form-select @error('mail_driver') is-invalid @enderror"
                        aria-describedby="mail_driver-help" required>
                    @php
                        $currentDriver = old('mail_driver', $settings['mail_driver']['value'] ?? 'log');
                    @endphp
                    @foreach (['log', 'smtp', 'ses', 'sendgrid', 'mailgun', 'postmark', 'resend', 'brevo'] as $driver)
                        <option value="{{ $driver }}" @selected($currentDriver === $driver)>{{ ucfirst($driver) }}</option>
                    @endforeach
                </select>
                <div id="mail_driver-help" class="form-text small">
                    Use "log" during development — emails are written to storage/logs/laravel.log instead of being sent.
                </div>
                @error('mail_driver')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="mail_host" class="form-label small fw-semibold">Host</label>
                    <input id="mail_host" name="mail_host" type="text"
                           class="form-control @error('mail_host') is-invalid @enderror"
                           value="{{ old('mail_host', $settings['mail_host']['value'] ?? '') }}">
                    @error('mail_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="mail_port" class="form-label small fw-semibold">Port</label>
                    <input id="mail_port" name="mail_port" type="number" min="1" max="65535"
                           class="form-control @error('mail_port') is-invalid @enderror"
                           value="{{ old('mail_port', $settings['mail_port']['value'] ?? 587) }}">
                    @error('mail_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="mail_encryption" class="form-label small fw-semibold">Encryption</label>
                <select id="mail_encryption" name="mail_encryption"
                        class="form-select @error('mail_encryption') is-invalid @enderror">
                    @php
                        $currentEncryption = old('mail_encryption', $settings['mail_encryption']['value'] ?? 'tls');
                    @endphp
                    <option value="tls" @selected($currentEncryption === 'tls')>TLS</option>
                    <option value="ssl" @selected($currentEncryption === 'ssl')>SSL</option>
                    <option value="none" @selected($currentEncryption === 'none')>None</option>
                </select>
                @error('mail_encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="mail_username" class="form-label small fw-semibold">
                    Username
                    @if ($settings['mail_username']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['mail_username']['hint'] }}</span>
                    @endif
                </label>
                <input id="mail_username" name="mail_username" type="text" autocomplete="off"
                       class="form-control @error('mail_username') is-invalid @enderror"
                       value="{{ old('mail_username') }}"
                       placeholder="{{ ($settings['mail_username']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('mail_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="mail_password" class="form-label small fw-semibold">
                    Password
                    @if ($settings['mail_password']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['mail_password']['hint'] }}</span>
                    @endif
                </label>
                <input id="mail_password" name="mail_password" type="password" autocomplete="off"
                       class="form-control @error('mail_password') is-invalid @enderror"
                       value="{{ old('mail_password') }}"
                       placeholder="{{ ($settings['mail_password']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('mail_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="mail_from_name" class="form-label small fw-semibold">From name</label>
                <input id="mail_from_name" name="mail_from_name" type="text"
                       class="form-control @error('mail_from_name') is-invalid @enderror"
                       value="{{ old('mail_from_name', $settings['mail_from_name']['value'] ?? '') }}" required>
                @error('mail_from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="mail_from_email" class="form-label small fw-semibold">From email</label>
                <input id="mail_from_email" name="mail_from_email" type="email"
                       class="form-control @error('mail_from_email') is-invalid @enderror"
                       value="{{ old('mail_from_email', $settings['mail_from_email']['value'] ?? '') }}" required>
                @error('mail_from_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label for="mail_reply_to" class="form-label small fw-semibold">Reply-to <span class="text-muted fw-normal">(optional)</span></label>
                <input id="mail_reply_to" name="mail_reply_to" type="email"
                       class="form-control @error('mail_reply_to') is-invalid @enderror"
                       value="{{ old('mail_reply_to', $settings['mail_reply_to']['value'] ?? '') }}">
                @error('mail_reply_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-ico-primary">Save email settings</button>
        </form>
    </div>

    {{-- Form B: send a test email --}}
    <div class="ico-card p-4 p-md-5 mb-4">
        <h2 class="h6 mb-3">Send a test email</h2>

        @error('test_recipient')
            <div class="alert alert-danger" role="alert" style="white-space: pre-wrap;">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('admin.settings.mail.test') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="test_recipient" class="form-label small fw-semibold">Recipient email</label>
                <input id="test_recipient" name="test_recipient" type="email"
                       class="form-control @error('test_recipient') is-invalid @enderror"
                       value="{{ old('test_recipient') }}" required
                       aria-describedby="test_recipient-help">
                <div id="test_recipient-help" class="form-text small">
                    Uses the settings currently saved above. Save first, then test.
                </div>
            </div>

            <button type="submit" class="btn btn-ico-outline">Send test email</button>
        </form>
    </div>
@endsection
