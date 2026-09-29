@extends('layouts.admin')

@section('title', $license->exists ? 'Edit licence' : 'New licence')
@section('subtitle', $license->exists ? 'Editing "'.$license->name.'".' : 'Define the terms an asset or plan can be issued under.')

@section('content')

    <div class="alert alert-info" role="alert">
        Editing licence text does not change licences already issued. Every download stores a
        snapshot of the terms as they were at that moment.
    </div>

    <form method="POST"
          action="{{ $license->exists ? route('admin.licenses.update', $license) : route('admin.licenses.store') }}"
          novalidate>
        @csrf
        @if ($license->exists)
            @method('PUT')
        @endif

        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Basics</h2>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label small fw-semibold">Name</label>
                    <input id="name" name="name" type="text" required maxlength="120"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $license->name) }}">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="slug" class="form-label small fw-semibold">Slug</label>
                    <input id="slug" name="slug" type="text" required maxlength="140"
                           class="form-control @error('slug') is-invalid @enderror"
                           value="{{ old('slug', $license->slug) }}"
                           aria-describedby="slug-help">
                    <div id="slug-help" class="form-text small">Lowercase, hyphenated. Must be unique.</div>
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="summary" class="form-label small fw-semibold">Summary</label>
                <textarea id="summary" name="summary" rows="3" required maxlength="500"
                          class="form-control @error('summary') is-invalid @enderror"
                          aria-describedby="summary-help">{{ old('summary', $license->summary) }}</textarea>
                <div id="summary-help" class="form-text small">The short version customers actually read.</div>
                @error('summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="full_text" class="form-label small fw-semibold">Full licence text</label>
                <textarea id="full_text" name="full_text" rows="16" required maxlength="60000"
                          class="form-control font-monospace @error('full_text') is-invalid @enderror"
                          aria-describedby="full_text-help" style="font-size: 0.85rem;">{{ old('full_text', $license->full_text) }}</textarea>
                <div id="full_text-help" class="form-text small">The full legally operative licence text.</div>
                @error('full_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Permissions</h2>

            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="allows_commercial" name="allows_commercial" value="1"
                               @checked(old('allows_commercial', $license->allows_commercial))>
                        <label class="form-check-label small fw-semibold" for="allows_commercial">Allows commercial use</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="allows_modification" name="allows_modification" value="1"
                               @checked(old('allows_modification', $license->allows_modification))>
                        <label class="form-check-label small fw-semibold" for="allows_modification">Allows modification</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="allows_redistribution" name="allows_redistribution" value="1"
                               @checked(old('allows_redistribution', $license->allows_redistribution))>
                        <label class="form-check-label small fw-semibold" for="allows_redistribution">Allows redistribution</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="requires_attribution" name="requires_attribution" value="1"
                               @checked(old('requires_attribution', $license->requires_attribution))>
                        <label class="form-check-label small fw-semibold" for="requires_attribution">Requires attribution</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_extended" name="is_extended" value="1"
                               @checked(old('is_extended', $license->is_extended))>
                        <label class="form-check-label small fw-semibold" for="is_extended">Extended licence</label>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $license->is_active))>
                        <label class="form-check-label small fw-semibold" for="is_active">Active</label>
                    </div>
                </div>
            </div>

            <div style="max-width: 12rem">
                <label for="sort_order" class="form-label small fw-semibold">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="9999"
                       class="form-control @error('sort_order') is-invalid @enderror"
                       value="{{ old('sort_order', $license->sort_order) }}">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-ico-primary">{{ $license->exists ? 'Save licence' : 'Create licence' }}</button>
            <a href="{{ route('admin.licenses.index') }}" class="btn btn-ico-outline">Cancel</a>
        </div>
    </form>
@endsection
