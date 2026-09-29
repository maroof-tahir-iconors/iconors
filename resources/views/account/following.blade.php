@extends('layouts.app')

@section('title', 'My Following — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    @if ($following->isEmpty())
        <div class="ico-card empty-state">
            <div class="empty-state__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h2 class="h6">You are not following anyone yet</h2>
            <p class="text-muted small mb-3">Follow contributors to keep track of what they publish.</p>
            <a href="{{ route('assets.index') }}" class="btn btn-ico-primary btn-sm">Discover contributors</a>
        </div>
    @else
        <div class="row g-3">
            @foreach ($following as $contributor)
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="ico-card follow-card">
                        <span class="follow-card__avatar" aria-hidden="true">
                            @if ($contributor->avatar)
                                <img src="{{ $contributor->avatar }}" alt="" width="52" height="52"
                                     loading="lazy" decoding="async">
                            @else
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($contributor->display_name, 0, 1)) }}
                            @endif
                        </span>

                        <div class="follow-card__body">
                            <h2 class="follow-card__name">
                                {{ $contributor->display_name }}
                                @if ($contributor->is_verified)
                                    <i class="bi bi-patch-check-fill follow-card__verified"
                                       aria-label="Verified contributor" role="img"></i>
                                @endif
                            </h2>
                            <p class="follow-card__meta">
                                {{ trans_choice(':count asset|:count assets', $contributor->assets_count, ['count' => number_format($contributor->assets_count)]) }}
                                <span aria-hidden="true">·</span>
                                Following since {{ $contributor->pivot->created_at?->format('M Y') ?? '—' }}
                            </p>
                        </div>

                        <div class="follow-card__actions">
                            <a href="{{ route('assets.index', ['contributor' => $contributor->slug]) }}"
                               class="btn btn-ico-outline btn-sm">View assets</a>

                            <form method="POST" action="{{ route('account.following.destroy', $contributor) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ico-ghost btn-sm">Unfollow</button>
                            </form>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        @if ($following->hasPages())
            <div class="mt-4">{{ $following->links() }}</div>
        @endif
    @endif

</div>
@endsection
