@extends('layouts.app')

@section('title', $post->title.' — Iconors Blog')
@section('meta_description', Str::limit(strip_tags($post->excerpt ?? $post->title), 155))

{{-- ---------------------------------------------------------------
     Article structured data (brief §29). Built in a raw PHP block
     rather than the json directive or an inline array inside a
     directive's parentheses — see pricing.blade.php for why.
--------------------------------------------------------------- --}}
@push('meta')
    @php
        $articleSchema = array_filter([
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post->title,
            'description'   => Str::limit(strip_tags($post->excerpt ?? ''), 300),
            'datePublished' => optional($post->published_at)->toIso8601String(),
            'url'           => route('blog.post', $post->slug),
            'author'        => $post->author
                ? ['@type' => 'Person', 'name' => $post->author->name]
                : null,
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')

<div class="container-ico px-3 py-4">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('blog') }}">Blog</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $post->title }}</li>
        </ol>
    </nav>

    <article>
        <header class="mb-4" style="max-width:72ch">
            @if ($post->blogCategory)
                <span class="badge-free mb-3">{{ $post->blogCategory->name }}</span>
            @endif
            <h1 class="mb-3">{{ $post->title }}</h1>
            <p class="text-muted small mb-0">
                @if ($post->author)
                    By {{ $post->author->name }} ·
                @endif
                {{ $post->published_at->format('j M Y') }} ·
                {{ $post->reading_minutes }} min read
            </p>
        </header>

        @if ($post->featured_image)
            <img src="{{ Storage::disk('public')->url($post->featured_image) }}" alt=""
                 class="ico-card mb-4" style="width:100%; max-width:72ch; aspect-ratio:16/9; object-fit:cover">
        @endif

        <div class="prose">
            {!! $post->content !!}
        </div>
    </article>

    {{-- ---------------------------------------------------------------
         Related posts
    --------------------------------------------------------------- --}}
    @if ($related->isNotEmpty())
        <section class="section--tight" data-ico-reveal>
            <div class="section-head">
                <div>
                    <h2>Related posts</h2>
                </div>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-3">
                @foreach ($related as $relatedPost)
                    <div class="col">
                        <article class="ico-card h-100 d-flex flex-column">
                            <a href="{{ route('blog.post', $relatedPost->slug) }}" class="text-decoration-none">
                                @if ($relatedPost->featured_image)
                                    <img src="{{ Storage::disk('public')->url($relatedPost->featured_image) }}"
                                         alt="" style="width:100%; aspect-ratio:16/9; object-fit:cover">
                                @else
                                    <div style="width:100%; aspect-ratio:16/9; background:var(--ico-gradient-signature)"
                                         aria-hidden="true"></div>
                                @endif
                            </a>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <h3 class="h6 mb-2">
                                    <a class="text-decoration-none" style="color:var(--ico-heading)"
                                       href="{{ route('blog.post', $relatedPost->slug) }}">
                                        {{ $relatedPost->title }}
                                    </a>
                                </h3>
                                <p class="text-muted small mt-auto mb-0">
                                    {{ $relatedPost->published_at->format('j M Y') }} ·
                                    {{ $relatedPost->reading_minutes }} min read
                                </p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

@endsection
