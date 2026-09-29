@extends('layouts.admin')

@section('title', 'Payment settings')
@section('subtitle', 'Configure the payment gateways customers can pay with at checkout.')

@section('content')

    <div class="alert alert-info" role="alert">
        Credentials are encrypted before they are stored and are never displayed again after saving.
        Leave a field blank to keep its current value.
    </div>

    <div class="alert alert-warning" role="alert">
        Switching a gateway to live mode means real customers are charged real money.
        Test thoroughly in test/sandbox mode first.
    </div>

    <form method="POST" action="{{ route('admin.settings.payments.update') }}" novalidate>
        @csrf

        {{-- Stripe --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Stripe</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="stripe_enabled" name="stripe_enabled" value="1"
                           @checked(old('stripe_enabled', $settings['stripe_enabled']['value'] ?? false))
                           aria-describedby="stripe_enabled-help">
                    <label class="form-check-label small fw-semibold" for="stripe_enabled">Enable Stripe</label>
                </div>
                <div id="stripe_enabled-help" class="form-text small">
                    Requires a saved secret key before it can be turned on.
                </div>
                @error('stripe_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="stripe_mode" class="form-label small fw-semibold">Mode</label>
                <select id="stripe_mode" name="stripe_mode"
                        class="form-select @error('stripe_mode') is-invalid @enderror">
                    @php
                        $currentStripeMode = old('stripe_mode', $settings['stripe_mode']['value'] ?? 'test');
                    @endphp
                    <option value="test" @selected($currentStripeMode === 'test')>Test</option>
                    <option value="live" @selected($currentStripeMode === 'live')>Live</option>
                </select>
                @error('stripe_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="stripe_key" class="form-label small fw-semibold">
                    Publishable key
                    @if ($settings['stripe_key']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['stripe_key']['hint'] }}</span>
                    @endif
                </label>
                <input id="stripe_key" name="stripe_key" type="text" autocomplete="off"
                       class="form-control @error('stripe_key') is-invalid @enderror"
                       value="{{ old('stripe_key') }}"
                       placeholder="{{ ($settings['stripe_key']['configured'] ?? false) ? 'Leave blank to keep current value' : 'pk_test_…' }}">
                @error('stripe_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="stripe_secret" class="form-label small fw-semibold">
                    Secret key
                    @if ($settings['stripe_secret']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['stripe_secret']['hint'] }}</span>
                    @endif
                </label>
                <input id="stripe_secret" name="stripe_secret" type="password" autocomplete="off"
                       class="form-control @error('stripe_secret') is-invalid @enderror"
                       value="{{ old('stripe_secret') }}"
                       placeholder="{{ ($settings['stripe_secret']['configured'] ?? false) ? 'Leave blank to keep current value' : 'sk_test_…' }}">
                @error('stripe_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-2">
                <label for="stripe_webhook_secret" class="form-label small fw-semibold">
                    Webhook signing secret
                    @if ($settings['stripe_webhook_secret']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['stripe_webhook_secret']['hint'] }}</span>
                    @endif
                </label>
                <input id="stripe_webhook_secret" name="stripe_webhook_secret" type="password" autocomplete="off"
                       class="form-control @error('stripe_webhook_secret') is-invalid @enderror"
                       value="{{ old('stripe_webhook_secret') }}"
                       placeholder="{{ ($settings['stripe_webhook_secret']['configured'] ?? false) ? 'Leave blank to keep current value' : 'whsec_…' }}"
                       aria-describedby="stripe-keys-help">
                @error('stripe_webhook_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <p id="stripe-keys-help" class="form-text small mb-0">Get test keys at dashboard.stripe.com/test/apikeys</p>
        </div>

        {{-- PayPal --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">PayPal</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="paypal_enabled" name="paypal_enabled" value="1"
                           @checked(old('paypal_enabled', $settings['paypal_enabled']['value'] ?? false))
                           aria-describedby="paypal_enabled-help">
                    <label class="form-check-label small fw-semibold" for="paypal_enabled">Enable PayPal</label>
                </div>
                <div id="paypal_enabled-help" class="form-text small">
                    Requires a saved secret before it can be turned on.
                </div>
                @error('paypal_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="paypal_mode" class="form-label small fw-semibold">Mode</label>
                <select id="paypal_mode" name="paypal_mode"
                        class="form-select @error('paypal_mode') is-invalid @enderror">
                    @php
                        $currentPaypalMode = old('paypal_mode', $settings['paypal_mode']['value'] ?? 'sandbox');
                    @endphp
                    <option value="sandbox" @selected($currentPaypalMode === 'sandbox')>Sandbox</option>
                    <option value="live" @selected($currentPaypalMode === 'live')>Live</option>
                </select>
                @error('paypal_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="paypal_client_id" class="form-label small fw-semibold">
                    Client ID
                    @if ($settings['paypal_client_id']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['paypal_client_id']['hint'] }}</span>
                    @endif
                </label>
                <input id="paypal_client_id" name="paypal_client_id" type="password" autocomplete="off"
                       class="form-control @error('paypal_client_id') is-invalid @enderror"
                       value="{{ old('paypal_client_id') }}"
                       placeholder="{{ ($settings['paypal_client_id']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('paypal_client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="paypal_secret" class="form-label small fw-semibold">
                    Secret
                    @if ($settings['paypal_secret']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['paypal_secret']['hint'] }}</span>
                    @endif
                </label>
                <input id="paypal_secret" name="paypal_secret" type="password" autocomplete="off"
                       class="form-control @error('paypal_secret') is-invalid @enderror"
                       value="{{ old('paypal_secret') }}"
                       placeholder="{{ ($settings['paypal_secret']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('paypal_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-2">
                <label for="paypal_webhook_id" class="form-label small fw-semibold">
                    Webhook ID
                    @if ($settings['paypal_webhook_id']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['paypal_webhook_id']['hint'] }}</span>
                    @endif
                </label>
                <input id="paypal_webhook_id" name="paypal_webhook_id" type="password" autocomplete="off"
                       class="form-control @error('paypal_webhook_id') is-invalid @enderror"
                       value="{{ old('paypal_webhook_id') }}"
                       placeholder="{{ ($settings['paypal_webhook_id']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}"
                       aria-describedby="paypal-keys-help">
                @error('paypal_webhook_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <p id="paypal-keys-help" class="form-text small mb-0">Create sandbox credentials at developer.paypal.com/dashboard</p>
        </div>

        {{-- Payoneer --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Payoneer</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="payoneer_enabled" name="payoneer_enabled" value="1"
                           @checked(old('payoneer_enabled', $settings['payoneer_enabled']['value'] ?? false))
                           aria-describedby="payoneer_enabled-help">
                    <label class="form-check-label small fw-semibold" for="payoneer_enabled">Enable Payoneer</label>
                </div>
                <div id="payoneer_enabled-help" class="form-text small">
                    Requires a saved API password before it can be turned on.
                </div>
                @error('payoneer_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="payoneer_mode" class="form-label small fw-semibold">Mode</label>
                <select id="payoneer_mode" name="payoneer_mode"
                        class="form-select @error('payoneer_mode') is-invalid @enderror">
                    @php
                        $currentPayoneerMode = old('payoneer_mode', $settings['payoneer_mode']['value'] ?? 'sandbox');
                    @endphp
                    <option value="sandbox" @selected($currentPayoneerMode === 'sandbox')>Sandbox</option>
                    <option value="live" @selected($currentPayoneerMode === 'live')>Live</option>
                </select>
                @error('payoneer_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="payoneer_program_id" class="form-label small fw-semibold">
                    Program ID
                    @if ($settings['payoneer_program_id']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['payoneer_program_id']['hint'] }}</span>
                    @endif
                </label>
                <input id="payoneer_program_id" name="payoneer_program_id" type="password" autocomplete="off"
                       class="form-control @error('payoneer_program_id') is-invalid @enderror"
                       value="{{ old('payoneer_program_id') }}"
                       placeholder="{{ ($settings['payoneer_program_id']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('payoneer_program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="payoneer_api_username" class="form-label small fw-semibold">
                    API username
                    @if ($settings['payoneer_api_username']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['payoneer_api_username']['hint'] }}</span>
                    @endif
                </label>
                <input id="payoneer_api_username" name="payoneer_api_username" type="password" autocomplete="off"
                       class="form-control @error('payoneer_api_username') is-invalid @enderror"
                       value="{{ old('payoneer_api_username') }}"
                       placeholder="{{ ($settings['payoneer_api_username']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('payoneer_api_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="payoneer_api_password" class="form-label small fw-semibold">
                    API password
                    @if ($settings['payoneer_api_password']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['payoneer_api_password']['hint'] }}</span>
                    @endif
                </label>
                <input id="payoneer_api_password" name="payoneer_api_password" type="password" autocomplete="off"
                       class="form-control @error('payoneer_api_password') is-invalid @enderror"
                       value="{{ old('payoneer_api_password') }}"
                       placeholder="{{ ($settings['payoneer_api_password']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}">
                @error('payoneer_api_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-2">
                <label for="payoneer_webhook_secret" class="form-label small fw-semibold">
                    Webhook secret
                    @if ($settings['payoneer_webhook_secret']['configured'] ?? false)
                        <span class="badge text-bg-light fw-normal">Saved {{ $settings['payoneer_webhook_secret']['hint'] }}</span>
                    @endif
                </label>
                <input id="payoneer_webhook_secret" name="payoneer_webhook_secret" type="password" autocomplete="off"
                       class="form-control @error('payoneer_webhook_secret') is-invalid @enderror"
                       value="{{ old('payoneer_webhook_secret') }}"
                       placeholder="{{ ($settings['payoneer_webhook_secret']['configured'] ?? false) ? 'Leave blank to keep current value' : '' }}"
                       aria-describedby="payoneer-keys-help">
                @error('payoneer_webhook_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <p id="payoneer-keys-help" class="form-text small mb-0">
                Find these under your Payoneer merchant programme in the Payoneer dashboard.
                Credentials are encrypted before storage and never displayed again.
            </p>
        </div>

        {{-- Bank transfer --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Bank transfer</h2>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="bank_transfer_enabled" name="bank_transfer_enabled" value="1"
                           @checked(old('bank_transfer_enabled', $settings['bank_transfer_enabled']['value'] ?? false))>
                    <label class="form-check-label small fw-semibold" for="bank_transfer_enabled">Enable bank transfer</label>
                </div>
                @error('bank_transfer_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="bank_transfer_instructions" class="form-label small fw-semibold">Instructions</label>
                <textarea id="bank_transfer_instructions" name="bank_transfer_instructions" rows="5"
                          class="form-control @error('bank_transfer_instructions') is-invalid @enderror"
                          aria-describedby="bank_transfer_instructions-help">{{ old('bank_transfer_instructions', $settings['bank_transfer_instructions']['value'] ?? '') }}</textarea>
                <div id="bank_transfer_instructions-help" class="form-text small">
                    Shown to the customer at checkout.
                </div>
                @error('bank_transfer_instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save payment settings</button>
    </form>
@endsection
