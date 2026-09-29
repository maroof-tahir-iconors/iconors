@extends('layouts.app')

@section('title', 'My Collections — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    <div class="row g-3">

        {{-- The create tile leads the grid, as in the reference design: on an
             empty account it is the only thing on screen, so it doubles as the
             empty state and no separate placeholder is needed. --}}
        <div class="col-6 col-md-4 col-xl-3">
            <button type="button" class="collection-new" data-bs-toggle="modal"
                    data-bs-target="#createCollectionModal">
                <span class="collection-new__plus" aria-hidden="true">+</span>
                <span class="collection-new__label">Create New Collection</span>
            </button>
        </div>

        @foreach ($collections as $collection)
            <div class="col-6 col-md-4 col-xl-3">
                <article class="ico-card collection-card">
                    <div class="collection-card__grid">
                        @php $shown = $collection->assets->take(4); @endphp

                        @forelse ($shown as $asset)
                            @php
                                $preview = $asset->previews->firstWhere('is_primary', true)
                                    ?? $asset->previews->first();
                            @endphp
                            <span class="collection-card__cell">
                                @if ($preview)
                                    <img src="{{ Storage::disk($preview->disk)->url($preview->path) }}"
                                         alt="" loading="lazy" decoding="async" width="80" height="80">
                                @else
                                    <i class="bi bi-image" aria-hidden="true"></i>
                                @endif
                            </span>
                        @empty
                            <span class="collection-card__cell collection-card__cell--empty">
                                <i class="bi bi-collection" aria-hidden="true"></i>
                            </span>
                        @endforelse

                        {{-- Pad to four cells so every tile keeps the same 2x2
                             shape regardless of how many assets it holds. --}}
                        @if ($shown->isNotEmpty())
                            @for ($i = $shown->count(); $i < 4; $i++)
                                <span class="collection-card__cell collection-card__cell--empty"></span>
                            @endfor
                        @endif
                    </div>

                    <div class="collection-card__body">
                        <h2 class="collection-card__name">{{ $collection->name }}</h2>
                        <p class="collection-card__meta">
                            {{ trans_choice(':count asset|:count assets', $collection->assets_count, ['count' => number_format($collection->assets_count)]) }}
                            @if ($collection->is_public)
                                <span class="collection-card__dot" aria-hidden="true">·</span>
                                <span>Public</span>
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('account.collections.destroy', $collection) }}"
                          class="collection-card__delete"
                          onsubmit="return confirm('Delete “{{ $collection->name }}”? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ico-ghost btn-sm"
                                aria-label="Delete collection {{ $collection->name }}">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </article>
            </div>
        @endforeach

    </div>

    @if ($collections->hasPages())
        <div class="mt-4">{{ $collections->links() }}</div>
    @endif

</div>

{{-- Create collection modal --}}
<div class="modal fade" id="createCollectionModal" tabindex="-1"
     aria-labelledby="createCollectionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('account.collections.store') }}">
            @csrf

            <div class="modal-header">
                <h2 class="modal-title h6" id="createCollectionLabel">Create a collection</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label for="collection-name" class="form-label">Name</label>
                    <input id="collection-name" name="name" type="text" maxlength="120" required
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="e.g. Dashboard icons">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="collection-description" class="form-label">
                        Description <span class="text-muted small">(optional)</span>
                    </label>
                    <textarea id="collection-description" name="description" rows="2" maxlength="500"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="What belongs in here?">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1"
                           id="collection-public" name="is_public" @checked(old('is_public'))>
                    <label class="form-check-label" for="collection-public">
                        Make this collection public
                        <span class="d-block text-muted small">Anyone with the link can view it.</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-ico-primary btn-sm">Create collection</button>
            </div>
        </form>
    </div>
</div>

{{-- Validation failures redirect back with errors; reopen the modal so the
     user sees them instead of an apparently unchanged page. --}}
@if ($errors->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('createCollectionModal');
                if (el && window.bootstrap) { new window.bootstrap.Modal(el).show(); }
            });
        </script>
    @endpush
@endif
@endsection
