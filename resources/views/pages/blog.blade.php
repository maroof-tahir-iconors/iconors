@extends('layouts.app')
@inject('settings', 'App\Services\Settings\SettingsService')

@section('title', (request('category') ? 'Blog — '.request('category').' — ' : 'Blog — ').$settings->get('site_name', 'Iconors'))
@section('meta_description', 'Design, product and tutorial writing from the Iconors team — icon systems, animation and how the marketplace works.')

@section('content')

{{-- ---------------------------------------------------------------
     Hero
--------------------------------------------------------------- --}}
<section class="ico-hero" data-ico-hero>
    <div class="container-ico text-center position-relative">
        <span class="ico-hero__label" data-ico-hero-item>Blog</span>
        <h1 class="ico-hero__title" data-ico-hero-item>Notes on design and product</h1>
        <p class="ico-hero__lead mb-0" data-ico-hero-item>
            Craft, process and the occasional behind-the-scenes look at building Iconors.
        </p>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Category filter
--------------------------------------------------------------- --}}
<section class="section--tight pb-0" data-ico-reveal>
    <div class="container-ico">
        <h2 class="visually-hidden">Filter posts by category</h2>
        <div class="d-flex flex-wrap gap-2" role="list" aria-label="Post categories">
            <a class="btn btn-sm {{ request('category') ? 'btn-ico-outline' : 'btn-ico-primary' }}"
               href="{{ route('blog') }}"
               @if (! request('category')) aria-current="page" @endif>
                All
            </a>
            @foreach ($categories as $category)
                <a class="btn btn-sm {{ request('category') === $category->slug ? 'btn-ico-primary' : 'btn-ico-outline' }}"
                   href="{{ route('blog', ['category' => $category->slug]) }}"
                   @if (request('category') === $category->slug) aria-current="page" @endif>
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------
     Posts
--------------------------------------------------------------- --}}
<section class="section" data-ico-reveal>
    <div class="container-ico">
        @if ($posts->isEmpty())
            <div class="empty-state ico-card">
                <div class="empty-state__icon"><i class="bi bi-journal-text" aria-hidden="true"></i></div>
                <h2 class="h5">No posts found</h2>
                <p class="text-muted small mb-3">
                    @if (request('category'))
                        Nothing has been published in this category yet.
                    @else
                        Nothing has been published yet. Check back soon.
                    @endif
                </p>
                <a class="btn btn-ico-primary btn-sm" href="{{ route('blog') }}">View all posts</a>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                @foreach ($posts as $post)
                    <div class="col">
                        <article class="ico-card h-100 d-flex flex-column">
                            <a href="{{ route('blog.post', $post->slug) }}" class="text-decoration-none">
                                @if ($post->featured_image)
                                    <img src="{{ Storage::disk('public')->url($post->featured_image) }}"
                                         alt="" style="width:100%; aspect-ratio:16/9; object-fit:cover">
                                @else
                                    <div style="width:100%; aspect-ratio:16/9; background:var(--ico-gradient-signature)"
                                         aria-hidden="true"></div>
                                @endif
                            </a>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                @if ($post->blogCategory)
                                    <span class="badge-free align-self-start mb-2">{{ $post->blogCategory->name }}</span>
                                @endif
                                <h3 class="h6 mb-2">
                                    <a class="text-decoration-none" style="color:var(--ico-heading)"
                                       href="{{ route('blog.post', $post->slug) }}">
                                        {{ $post->title }}
                                    </a>
                                </h3>
                                <p class="text-muted small mb-3">{{ Str::limit($post->excerpt, 120) }}</p>
                                <p class="text-muted small mt-auto mb-0">
                                    @if ($post->author)
                                        {{ $post->author->name }} ·
                                    @endif
                                    {{ $post->published_at->format('j M Y') }} ·
                                    {{ $post->reading_minutes }} min read
                                </p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</section>

@endsection
