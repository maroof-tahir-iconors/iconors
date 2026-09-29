@extends('layouts.app')

@section('title', ($q = request('q')) ? "Search: {$q} — Iconors" : 'Browse assets — Iconors')

@section('content')
<div class="container-ico px-3 py-4">

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">
                @if (request('q'))
                    Results for “{{ request('q') }}”
                @else
                    Browse assets
                @endif
            </h1>
            <p class="text-muted small mb-0">
                {{ number_format($assets->total()) }}
                {{ Str::plural('asset', $assets->total()) }}
            </p>
        </div>

        <form method="GET" class="d-flex align-items-center gap-2">
            {{-- Carry existing filters so changing sort does not reset them. --}}
            @foreach (request()->except(['sort', 'page']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <label for="sort" class="small text-muted mb-0">Sort</label>
            <select id="sort" name="sort" class="form-select form-select-sm w-auto"
                    onchange="this.form.submit()">
                @foreach ([
                    'relevance' => 'Relevance',
                    'trending'  => 'Trending',
                    'newest'    => 'Newest',
                    'oldest'    => 'Oldest',
                    'downloads' => 'Most downloaded',
                    'views'     => 'Most viewed',
                    'likes'     => 'Most liked',
                    'price_asc' => 'Price: low to high',
                    'price_desc'=> 'Price: high to low',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="row g-4">
        {{-- Filters. On mobile these collapse into a drawer (brief §35). --}}
        <aside class="col-lg-3">
            <button class="btn btn-ico-outline w-100 d-lg-none mb-3" type="button"
                    data-bs-toggle="collapse" data-bs-target="#icoFilters"
                    aria-expanded="false" aria-controls="icoFilters">
                <i class="bi bi-sliders me-1" aria-hidden="true"></i> Filters
            </button>

            <form method="GET" class="collapse d-lg-block" id="icoFilters">
                @if (request('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif
                @if (request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif

                <div class="ico-card filter-card">
                    <h2>Price</h2>
                    @foreach (['' => 'All', 'free' => 'Free', 'premium' => 'Premium'] as $value => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="price"
                                   id="price-{{ $value ?: 'all' }}" value="{{ $value }}"
                                   @checked(request('price', '') === $value)
                                   onchange="this.form.submit()">
                            <label class="form-check-label small" for="price-{{ $value ?: 'all' }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>

                <div class="ico-card filter-card">
                    <h2>Asset type</h2>
                    <div class="d-grid gap-1">
                        <a class="filter-link {{ request('type') ? '' : 'is-active' }}"
                           href="{{ route('assets.index', request()->except(['type', 'page'])) }}">All types</a>
                        @foreach ($assetTypes as $type)
                            <a class="filter-link {{ request('type') === $type->slug ? 'is-active' : '' }}"
                               href="{{ route('assets.index', array_merge(request()->except('page'), ['type' => $type->slug])) }}">
                                {{ $type->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="ico-card filter-card">
                    <h2>Category</h2>
                    <div class="d-grid gap-1" style="max-height:260px; overflow-y:auto">
                        <a class="filter-link {{ request('category') ? '' : 'is-active' }}"
                           href="{{ route('assets.index', request()->except(['category', 'page'])) }}">All categories</a>
                        @foreach ($categories as $category)
                            <a class="filter-link {{ request('category') === $category->slug ? 'is-active' : '' }}"
                               href="{{ route('assets.index', array_merge(request()->except('page'), ['category' => $category->slug])) }}">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="ico-card filter-card">
                    <h2>Attributes</h2>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="animated" value="1"
                               id="f-animated" @checked(request('animated') === '1')
                               onchange="this.form.submit()">
                        <label class="form-check-label small" for="f-animated">Animated</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="threed" value="1"
                               id="f-3d" @checked(request('threed') === '1')
                               onchange="this.form.submit()">
                        <label class="form-check-label small" for="f-3d">3D</label>
                    </div>
                </div>
            </form>
        </aside>

        <div class="col-lg-9">
            @if ($assets->isEmpty())
                {{-- Empty state (brief §42). A zero-result search is a dead
                     end unless it offers a way out. --}}
                <div class="empty-state ico-card">
                    <div class="empty-state__icon"><i class="bi bi-search" aria-hidden="true"></i></div>
                    <h2 class="h5">No assets found</h2>
                    <p class="text-muted small mb-3">
                        @if (request('q'))
                            Nothing matched “{{ request('q') }}”. Try a broader term or clear your filters.
                        @else
                            No assets match these filters yet.
                        @endif
                    </p>
                    <a class="btn btn-ico-primary btn-sm" href="{{ route('assets.index') }}">Clear all filters</a>
                </div>
            @else
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
                    @foreach ($assets as $asset)
                        <div class="col">
                            {{-- The first row is above the fold on most viewports,
                                 so those images load eagerly. --}}
                            <x-asset-card :asset="$asset" :eager="$loop->index < 4" />
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $assets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
