@extends('layouts.admin')

@section('title', $plan->exists ? 'Edit plan' : 'New plan')
@section('subtitle', $plan->exists ? 'Editing "'.$plan->name.'". Price and limit changes apply to future billing cycles.' : 'Every price, limit and entitlement here is a database row — nothing about pricing is hard-coded.')

@php
    // Repeatable "features" rows. After a failed submit, rebuild rows from
    // the posted arrays (old()) so the admin doesn't lose their edits;
    // otherwise seed rows from the plan's saved features.
    $oldFeatureLabels = old('feature_label');

    if (is_array($oldFeatureLabels)) {
        $featureRows = [];
        foreach ($oldFeatureLabels as $i => $label) {
            $featureRows[] = [
                'label'       => $label,
                'included'    => (string) old('feature_included.'.$i, '1') === '1',
                'highlighted' => (string) old('feature_highlighted.'.$i, '0') === '1',
            ];
        }
    } else {
        $featureRows = $plan->features->sortBy('sort_order')->values()->map(function ($feature) {
            return [
                'label'       => $feature->label,
                'included'    => (bool) $feature->is_included,
                'highlighted' => (bool) $feature->is_highlighted,
            ];
        })->all();
    }
@endphp

@section('content')

    <form method="POST"
          action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}"
          novalidate>
        @csrf
        @if ($plan->exists)
            @method('PUT')
        @endif

        {{-- Basics --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Basics</h2>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold">Name</label>
                    <input id="name" name="name" type="text" required maxlength="120"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $plan->name) }}">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="slug" class="form-label small fw-semibold">Slug</label>
                    <input id="slug" name="slug" type="text" required maxlength="140"
                           class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $plan->slug) }}"
                           aria-describedby="slug-help">
                    <div id="slug-help" class="form-text small">Lowercase, hyphenated. Used to identify the plan on subscriptions.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-1">
                <label for="description" class="form-label small fw-semibold">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000"
                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $plan->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Pricing --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Pricing</h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="price_monthly" class="form-label small fw-semibold">Monthly price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input id="price_monthly" name="price_monthly" type="number" step="0.01" min="0" max="99999"
                               required class="form-control @error('price_monthly') is-invalid @enderror"
                               value="{{ old('price_monthly', $plan->price_monthly) }}">
                        @error('price_monthly')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="price_annual" class="form-label small fw-semibold">Annual price</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input id="price_annual" name="price_annual" type="number" step="0.01" min="0" max="99999"
                               required class="form-control @error('price_annual') is-invalid @enderror"
                               value="{{ old('price_annual', $plan->price_annual) }}">
                        @error('price_annual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-md-2">
                    <label for="currency" class="form-label small fw-semibold">Currency</label>
                    <input id="currency" name="currency" type="text" required maxlength="3"
                           class="form-control text-uppercase @error('currency') is-invalid @enderror"
                           value="{{ old('currency', $plan->currency ?? 'USD') }}">
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label for="trial_days" class="form-label small fw-semibold">Trial days</label>
                    <input id="trial_days" name="trial_days" type="number" min="0" max="365"
                           class="form-control @error('trial_days') is-invalid @enderror"
                           value="{{ old('trial_days', $plan->trial_days) }}">
                    @error('trial_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Gateway price IDs --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Gateway price IDs</h2>

            <div class="row g-3 mb-1">
                <div class="col-md-6">
                    <label for="stripe_monthly_price_id" class="form-label small fw-semibold">Stripe monthly price ID</label>
                    <input id="stripe_monthly_price_id" name="stripe_monthly_price_id" type="text" maxlength="255"
                           class="form-control @error('stripe_monthly_price_id') is-invalid @enderror"
                           value="{{ old('stripe_monthly_price_id', $plan->stripe_monthly_price_id) }}"
                           placeholder="price_…">
                    @error('stripe_monthly_price_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="stripe_annual_price_id" class="form-label small fw-semibold">Stripe annual price ID</label>
                    <input id="stripe_annual_price_id" name="stripe_annual_price_id" type="text" maxlength="255"
                           class="form-control @error('stripe_annual_price_id') is-invalid @enderror"
                           value="{{ old('stripe_annual_price_id', $plan->stripe_annual_price_id) }}"
                           placeholder="price_…"
                           aria-describedby="gateway-price-ids-help">
                    @error('stripe_annual_price_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="gateway-price-ids-help" class="form-text small mb-0">
                Optional. Leave blank until Stripe is connected; these map the plan to a Stripe Price object.
            </div>
        </div>

        {{-- Limits --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Limits</h2>

            <div class="row g-3 mb-1">
                <div class="col-md-3">
                    <label for="download_limit_daily" class="form-label small fw-semibold">Daily download limit</label>
                    <input id="download_limit_daily" name="download_limit_daily" type="number" min="0" max="100000"
                           class="form-control @error('download_limit_daily') is-invalid @enderror"
                           value="{{ old('download_limit_daily', $plan->download_limit_daily) }}"
                           aria-describedby="download-limits-help">
                    @error('download_limit_daily')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="download_limit_monthly" class="form-label small fw-semibold">Monthly download limit</label>
                    <input id="download_limit_monthly" name="download_limit_monthly" type="number" min="0" max="1000000"
                           class="form-control @error('download_limit_monthly') is-invalid @enderror"
                           value="{{ old('download_limit_monthly', $plan->download_limit_monthly) }}"
                           aria-describedby="download-limits-help">
                    @error('download_limit_monthly')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="credits_monthly" class="form-label small fw-semibold">Monthly credits</label>
                    <input id="credits_monthly" name="credits_monthly" type="number" min="0" max="1000000"
                           class="form-control @error('credits_monthly') is-invalid @enderror"
                           value="{{ old('credits_monthly', $plan->credits_monthly) }}">
                    @error('credits_monthly')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="team_seats" class="form-label small fw-semibold">Team seats</label>
                    <input id="team_seats" name="team_seats" type="number" min="1" max="10000"
                           class="form-control @error('team_seats') is-invalid @enderror"
                           value="{{ old('team_seats', $plan->team_seats) }}">
                    @error('team_seats')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div id="download-limits-help" class="form-text small mb-0">
                Leave BLANK for unlimited. Enter 0 for none. These mean different things.
            </div>
        </div>

        {{-- Entitlements --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Entitlements</h2>

            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="premium_access" name="premium_access" value="1"
                               @checked(old('premium_access', $plan->premium_access))>
                        <label class="form-check-label small fw-semibold" for="premium_access">Premium access</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="source_file_access" name="source_file_access" value="1"
                               @checked(old('source_file_access', $plan->source_file_access))>
                        <label class="form-check-label small fw-semibold" for="source_file_access">Source file access</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="editor_access" name="editor_access" value="1"
                               @checked(old('editor_access', $plan->editor_access))>
                        <label class="form-check-label small fw-semibold" for="editor_access">Editor access</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="api_access" name="api_access" value="1"
                               @checked(old('api_access', $plan->api_access))>
                        <label class="form-check-label small fw-semibold" for="api_access">API access</label>
                    </div>
                </div>
            </div>

            <div class="mb-1">
                <label for="license_id" class="form-label small fw-semibold">Licence</label>
                @php
                    $currentLicenseId = old('license_id', $plan->license_id);
                @endphp
                <select id="license_id" name="license_id"
                        class="form-select @error('license_id') is-invalid @enderror">
                    <option value="" @selected(blank($currentLicenseId))>None</option>
                    @foreach ($licenses as $license)
                        <option value="{{ $license->id }}" @selected((string) $currentLicenseId === (string) $license->id)>
                            {{ $license->name }}
                        </option>
                    @endforeach
                </select>
                @error('license_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Visibility --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Visibility</h2>

            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_team_plan" name="is_team_plan" value="1"
                               @checked(old('is_team_plan', $plan->is_team_plan))>
                        <label class="form-check-label small fw-semibold" for="is_team_plan">Team plan</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_featured" name="is_featured" value="1"
                               @checked(old('is_featured', $plan->is_featured))>
                        <label class="form-check-label small fw-semibold" for="is_featured">Featured</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $plan->is_active))>
                        <label class="form-check-label small fw-semibold" for="is_active">Active</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_public" name="is_public" value="1"
                               @checked(old('is_public', $plan->is_public))>
                        <label class="form-check-label small fw-semibold" for="is_public">Public</label>
                    </div>
                </div>
            </div>

            <div class="mt-3" style="max-width: 12rem">
                <label for="sort_order" class="form-label small fw-semibold">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999"
                       class="form-control @error('sort_order') is-invalid @enderror"
                       value="{{ old('sort_order', $plan->sort_order) }}">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Features list --}}
        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-1">Features</h2>
            <p class="text-muted small mb-3">
                These are display text for the pricing page. Actual access is controlled by the
                entitlement switches above.
            </p>

            <div id="ico-features-list">
                @forelse ($featureRows as $i => $row)
                    <div class="ico-feature-row row gx-2 gy-2 align-items-center mb-2 pb-2 border-bottom" data-feature-row>
                        <div class="col-12 col-md-6">
                            <label class="visually-hidden" data-feature-label-label for="feature_label_{{ $i }}">Feature label</label>
                            <input type="text" class="form-control form-control-sm" data-feature-label
                                   id="feature_label_{{ $i }}" name="feature_label[{{ $i }}]"
                                   value="{{ $row['label'] }}" placeholder="e.g. Unlimited downloads">
                        </div>
                        <div class="col-auto">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" data-feature-included-hidden name="feature_included[{{ $i }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" value="1"
                                       data-feature-included-checkbox
                                       id="feature_included_{{ $i }}" name="feature_included[{{ $i }}]"
                                       @checked($row['included'])>
                                <label class="form-check-label small" data-feature-included-label for="feature_included_{{ $i }}">Included</label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" data-feature-highlighted-hidden name="feature_highlighted[{{ $i }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" value="1"
                                       data-feature-highlighted-checkbox
                                       id="feature_highlighted_{{ $i }}" name="feature_highlighted[{{ $i }}]"
                                       @checked($row['highlighted'])>
                                <label class="form-check-label small" data-feature-highlighted-label for="feature_highlighted_{{ $i }}">Highlighted</label>
                            </div>
                        </div>
                        <div class="col-auto ms-auto">
                            <button type="button" class="btn btn-ico-outline btn-sm ico-remove-feature-row" aria-label="Remove this feature">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @empty
                @endforelse
            </div>

            <button type="button" id="ico-add-feature-row" class="btn btn-ico-outline btn-sm mt-1">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add feature
            </button>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-ico-primary">{{ $plan->exists ? 'Save plan' : 'Create plan' }}</button>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-ico-outline">Cancel</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var list = document.getElementById('ico-features-list');
        var addButton = document.getElementById('ico-add-feature-row');

        if (!list || !addButton) return;

        function reindexFeatureRows() {
            var rows = list.querySelectorAll('[data-feature-row]');

            rows.forEach(function (row, i) {
                var labelInput = row.querySelector('[data-feature-label]');
                var labelLabelEl = row.querySelector('[data-feature-label-label]');
                var includedHidden = row.querySelector('[data-feature-included-hidden]');
                var includedBox = row.querySelector('[data-feature-included-checkbox]');
                var includedLabelEl = row.querySelector('[data-feature-included-label]');
                var highlightedHidden = row.querySelector('[data-feature-highlighted-hidden]');
                var highlightedBox = row.querySelector('[data-feature-highlighted-checkbox]');
                var highlightedLabelEl = row.querySelector('[data-feature-highlighted-label]');

                if (labelInput) {
                    labelInput.name = 'feature_label[' + i + ']';
                    labelInput.id = 'feature_label_' + i;
                }
                if (labelLabelEl) labelLabelEl.setAttribute('for', 'feature_label_' + i);

                if (includedHidden) includedHidden.name = 'feature_included[' + i + ']';
                if (includedBox) {
                    includedBox.name = 'feature_included[' + i + ']';
                    includedBox.id = 'feature_included_' + i;
                }
                if (includedLabelEl) includedLabelEl.setAttribute('for', 'feature_included_' + i);

                if (highlightedHidden) highlightedHidden.name = 'feature_highlighted[' + i + ']';
                if (highlightedBox) {
                    highlightedBox.name = 'feature_highlighted[' + i + ']';
                    highlightedBox.id = 'feature_highlighted_' + i;
                }
                if (highlightedLabelEl) highlightedLabelEl.setAttribute('for', 'feature_highlighted_' + i);
            });
        }

        function featureRowHtml(index) {
            return '' +
                '<div class="ico-feature-row row gx-2 gy-2 align-items-center mb-2 pb-2 border-bottom" data-feature-row>' +
                '<div class="col-12 col-md-6">' +
                '<label class="visually-hidden" data-feature-label-label for="feature_label_' + index + '">Feature label</label>' +
                '<input type="text" class="form-control form-control-sm" data-feature-label id="feature_label_' + index + '" name="feature_label[' + index + ']" placeholder="e.g. Unlimited downloads">' +
                '</div>' +
                '<div class="col-auto">' +
                '<div class="form-check form-switch mb-0">' +
                '<input type="hidden" data-feature-included-hidden name="feature_included[' + index + ']" value="0">' +
                '<input class="form-check-input" type="checkbox" role="switch" value="1" checked data-feature-included-checkbox id="feature_included_' + index + '" name="feature_included[' + index + ']">' +
                '<label class="form-check-label small" data-feature-included-label for="feature_included_' + index + '">Included</label>' +
                '</div>' +
                '</div>' +
                '<div class="col-auto">' +
                '<div class="form-check form-switch mb-0">' +
                '<input type="hidden" data-feature-highlighted-hidden name="feature_highlighted[' + index + ']" value="0">' +
                '<input class="form-check-input" type="checkbox" role="switch" value="1" data-feature-highlighted-checkbox id="feature_highlighted_' + index + '" name="feature_highlighted[' + index + ']">' +
                '<label class="form-check-label small" data-feature-highlighted-label for="feature_highlighted_' + index + '">Highlighted</label>' +
                '</div>' +
                '</div>' +
                '<div class="col-auto ms-auto">' +
                '<button type="button" class="btn btn-ico-outline btn-sm ico-remove-feature-row" aria-label="Remove this feature">' +
                '<i class="bi bi-trash" aria-hidden="true"></i>' +
                '</button>' +
                '</div>' +
                '</div>';
        }

        addButton.addEventListener('click', function () {
            var nextIndex = list.querySelectorAll('[data-feature-row]').length;
            list.insertAdjacentHTML('beforeend', featureRowHtml(nextIndex));
            reindexFeatureRows();

            var rows = list.querySelectorAll('[data-feature-row]');
            var lastRow = rows[rows.length - 1];
            var lastInput = lastRow ? lastRow.querySelector('[data-feature-label]') : null;
            if (lastInput) lastInput.focus();
        });

        list.addEventListener('click', function (e) {
            var button = e.target.closest('.ico-remove-feature-row');
            if (!button) return;

            var row = button.closest('[data-feature-row]');
            if (row) row.remove();

            reindexFeatureRows();
        });
    })();
</script>
@endpush
