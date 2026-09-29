@extends('layouts.app')

@section('title', $page->title.' — Iconors')
@section('meta_description', Str::limit(strip_tags($page->content), 155))

@php
    // Build a table of contents from the h2 headings in the stored HTML and
    // inject matching ids so the sidebar links land somewhere.
    //
    // Done server-side rather than in JS: a document's structure belongs in
    // the markup search engines and screen readers receive, not assembled
    // after paint.
    $html     = $page->content ?? '';
    $sections = [];

    $html = preg_replace_callback('/<h2(?![^>]*\bid=)([^>]*)>(.*?)<\/h2>/is', function ($m) use (&$sections) {
        $text = trim(strip_tags($m[2]));
        $id   = Str::slug($text) ?: 'section-'.(count($sections) + 1);

        $sections[] = ['id' => $id, 'text' => $text];

        return '<h2 id="'.$id.'"'.$m[1].'>'.$m[2].'</h2>';
    }, $html);

    $supportEmail = app(App\Services\Settings\SettingsService::class)->get('support_email');
@endphp

@section('content')
<div class="container-ico section">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
        </ol>
    </nav>

    <header class="mb-4">
        <h1 class="mb-2" style="font-size:clamp(1.9rem,4vw,2.75rem); letter-spacing:-.03em">
            {{ $page->title }}
        </h1>
        <p class="small mb-0" style="color:var(--ico-muted)">
            Last updated {{ $page->updated_at->format('j F Y') }}
        </p>
    </header>

    <div class="row g-4 g-lg-5">
        {{-- Table of contents. Hidden below lg, where a sticky sidebar would
             consume most of the viewport. --}}
        @if (count($sections) > 1)
            <aside class="col-lg-3 order-lg-2 d-none d-lg-block">
                <nav class="toc" aria-labelledby="toc-heading">
                    <p class="toc__title" id="toc-heading">On this page</p>
                    <ul class="list-unstyled mb-0" role="list">
                        @foreach ($sections as $section)
                            <li>
                                <a class="toc__link" href="#{{ $section['id'] }}"
                                   data-ico-toc-link>{{ $section['text'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </aside>
        @endif

        <div class="{{ count($sections) > 1 ? 'col-lg-9 order-lg-1' : 'col-12' }}">
            <div class="ico-card p-4 p-md-5">
                <div class="prose">
                    {!! $html !!}
                </div>
            </div>

            {{-- A legal page is the end of a journey. Give the reader
                 somewhere to go rather than a dead end. --}}
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-ico-primary btn-sm" href="{{ route('assets.index') }}">Browse assets</a>
                <a class="btn btn-ico-outline btn-sm" href="{{ route('pricing') }}">See pricing</a>
                @if ($supportEmail)
                    <a class="btn btn-ico-outline btn-sm" href="mailto:{{ $supportEmail }}">Ask a question</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Highlights the section currently in view. Purely additive — the anchor
// links work perfectly well without it.
(function () {
    var links = document.querySelectorAll('[data-ico-toc-link]');
    if (!links.length || !('IntersectionObserver' in window)) return;

    var headings = [];
    links.forEach(function (link) {
        var el = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (el) headings.push({ el: el, link: link });
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            links.forEach(function (l) { l.classList.remove('is-active'); });
            var match = headings.find(function (h) { return h.el === entry.target; });
            if (match) match.link.classList.add('is-active');
        });
    }, { rootMargin: '-96px 0px -70% 0px' });

    headings.forEach(function (h) { observer.observe(h.el); });
})();
</script>
@endpush
