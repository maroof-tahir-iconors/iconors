@extends('layouts.admin')

@section('title', 'Royalties & payouts')
@section('subtitle', 'Set contributor commission rates and how payouts are calculated and released.')

@section('content')

    <div class="alert alert-info" role="alert">
        Rate changes affect FUTURE earnings only. Each order stores the commission rate that applied
        at the time of purchase, so existing contributor earnings are never altered retroactively.
    </div>

    <form method="POST" action="{{ route('admin.settings.royalties.update') }}" novalidate>
        @csrf
        <input type="hidden" name="_fields" value="payout_deduct_refunds">

        {{-- Commission rates --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Commission rates</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="commission_rate_one_time" class="form-label small fw-semibold">One-time purchases</label>
                    <div class="input-group">
                        <input id="commission_rate_one_time" name="commission_rate_one_time" type="number"
                               step="0.01" min="0" max="100"
                               class="form-control @error('commission_rate_one_time') is-invalid @enderror"
                               value="{{ old('commission_rate_one_time', $settings['commission_rate_one_time']['value'] ?? 50) }}"
                               aria-describedby="commission-rates-help">
                        <span class="input-group-text">%</span>
                        @error('commission_rate_one_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="commission_rate_subscription" class="form-label small fw-semibold">Subscription downloads</label>
                    <div class="input-group">
                        <input id="commission_rate_subscription" name="commission_rate_subscription" type="number"
                               step="0.01" min="0" max="100"
                               class="form-control @error('commission_rate_subscription') is-invalid @enderror"
                               value="{{ old('commission_rate_subscription', $settings['commission_rate_subscription']['value'] ?? 30) }}"
                               aria-describedby="commission-rates-help">
                        <span class="input-group-text">%</span>
                        @error('commission_rate_subscription')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="commission_rate_api" class="form-label small fw-semibold">API usage</label>
                    <div class="input-group">
                        <input id="commission_rate_api" name="commission_rate_api" type="number"
                               step="0.01" min="0" max="100"
                               class="form-control @error('commission_rate_api') is-invalid @enderror"
                               value="{{ old('commission_rate_api', $settings['commission_rate_api']['value'] ?? 20) }}"
                               aria-describedby="commission-rates-help">
                        <span class="input-group-text">%</span>
                        @error('commission_rate_api')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div id="commission-rates-help" class="form-text small mb-0">
                The percentage the CONTRIBUTOR receives. A 50% one-time rate means a $10 sale pays the contributor $5.
            </div>
        </div>

        {{-- Payouts --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Payouts</h2>

            <div class="row g-3 mb-1">
                <div class="col-md-4">
                    <label for="payout_minimum" class="form-label small fw-semibold">Minimum payout</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input id="payout_minimum" name="payout_minimum" type="number" step="0.01" min="0" max="100000"
                               class="form-control @error('payout_minimum') is-invalid @enderror"
                               value="{{ old('payout_minimum', $settings['payout_minimum']['value'] ?? 50) }}">
                        @error('payout_minimum')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="payout_holding_days" class="form-label small fw-semibold">Holding period (days)</label>
                    <input id="payout_holding_days" name="payout_holding_days" type="number" min="0" max="365"
                           class="form-control @error('payout_holding_days') is-invalid @enderror"
                           value="{{ old('payout_holding_days', $settings['payout_holding_days']['value'] ?? 14) }}"
                           aria-describedby="payout_holding_days-help">
                    @error('payout_holding_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="payout_schedule" class="form-label small fw-semibold">Payout schedule</label>
                    <select id="payout_schedule" name="payout_schedule"
                            class="form-select @error('payout_schedule') is-invalid @enderror">
                        @php
                            $currentSchedule = old('payout_schedule', $settings['payout_schedule']['value'] ?? 'monthly');
                        @endphp
                        <option value="manual" @selected($currentSchedule === 'manual')>Manual</option>
                        <option value="weekly" @selected($currentSchedule === 'weekly')>Weekly</option>
                        <option value="fortnightly" @selected($currentSchedule === 'fortnightly')>Fortnightly</option>
                        <option value="monthly" @selected($currentSchedule === 'monthly')>Monthly</option>
                    </select>
                    @error('payout_schedule')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="payout_holding_days-help" class="form-text small mb-3">
                How long earnings stay pending before they can be withdrawn. This is your protection
                against paying out on a sale that is later refunded.
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="payout_deduct_refunds" name="payout_deduct_refunds" value="1"
                           @checked(old('payout_deduct_refunds', $settings['payout_deduct_refunds']['value'] ?? false))>
                    <label class="form-check-label small fw-semibold" for="payout_deduct_refunds">Deduct refunds from payouts</label>
                </div>
                @error('payout_deduct_refunds')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="payout_fee_fixed" class="form-label small fw-semibold">Fixed payout fee</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input id="payout_fee_fixed" name="payout_fee_fixed" type="number" step="0.01" min="0" max="1000"
                               class="form-control @error('payout_fee_fixed') is-invalid @enderror"
                               value="{{ old('payout_fee_fixed', $settings['payout_fee_fixed']['value'] ?? 0) }}">
                        @error('payout_fee_fixed')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="payout_fee_percent" class="form-label small fw-semibold">Percentage payout fee</label>
                    <div class="input-group">
                        <input id="payout_fee_percent" name="payout_fee_percent" type="number" step="0.01" min="0" max="100"
                               class="form-control @error('payout_fee_percent') is-invalid @enderror"
                               value="{{ old('payout_fee_percent', $settings['payout_fee_percent']['value'] ?? 0) }}">
                        <span class="input-group-text">%</span>
                        @error('payout_fee_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Refunds --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Refunds</h2>

            <div class="mb-3">
                <label for="refund_window_days" class="form-label small fw-semibold">Refund window (days)</label>
                <input id="refund_window_days" name="refund_window_days" type="number" min="0" max="365"
                       class="form-control @error('refund_window_days') is-invalid @enderror"
                       value="{{ old('refund_window_days', $settings['refund_window_days']['value'] ?? 14) }}">
                @error('refund_window_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="refund_policy_summary" class="form-label small fw-semibold">Refund policy summary</label>
                <textarea id="refund_policy_summary" name="refund_policy_summary" rows="5"
                          class="form-control @error('refund_policy_summary') is-invalid @enderror"
                          aria-describedby="refund_policy_summary-help">{{ old('refund_policy_summary', $settings['refund_policy_summary']['value'] ?? '') }}</textarea>
                <div id="refund_policy_summary-help" class="form-text small">
                    Shown on the refund policy page and at checkout.
                </div>
                @error('refund_policy_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save royalty settings</button>
    </form>
@endsection
