@inject('settings', 'App\Services\Settings\SettingsService')

<footer class="ico-footer mt-4">
    <div class="container-ico">
        <div class="row g-4">
            <div class="col-lg-4">
                <span class="ico-logo mb-3">
                    <span class="ico-logo__mark" aria-hidden="true">
                        <svg width="19" height="19" viewBox="0 0 28 28" fill="none">
                            <path d="M5.4 5.3h3.6v3.6H5.4zM23.4 12h-3.3c-.2-1.7-1.5-3-3.3-3.2h-3.6V5.3h5.2c2.2 0 4 1.8 4 4zM23.4 15.4v3.8c0 2.1-1.8 3.6-3.9 3.6H10c-2.2 0-3.9-1.7-3.9-3.8V12h3.5v3.3c0 2.2 1.7 3.8 3.9 3.8h2.6c2.1 0 3.6-1.4 3.8-3.4z"
                                  fill="currentColor"/>
                        </svg>
                    </span>
                    <span>{{ $settings->get('site_name', 'Iconors') }}</span>
                </span>
                <p class="mb-0" style="max-width:36ch; font-size:.9rem; color:var(--ico-muted)">
                    {{ $settings->get('site_tagline') }}
                </p>
            </div>

            <div class="col-6 col-lg-2">
                <h2>Explore</h2>
                <ul class="list-unstyled mb-0" role="list">
                    <li><a href="{{ route('assets.index') }}">All assets</a></li>
                    <li><a href="{{ route('assets.index', ['price' => 'free']) }}">Free assets</a></li>
                    <li><a href="{{ route('assets.index', ['sort' => 'trending']) }}">Trending</a></li>
                    <li><a href="{{ route('assets.index', ['sort' => 'newest']) }}">New releases</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h2>Contribute</h2>
                <ul class="list-unstyled mb-0" role="list">
                    @php
                        $isContributor = auth()->check() && auth()->user()->contributor?->status === 'approved';
                    @endphp

                    @if ($isContributor)
                        {{-- An approved contributor has no use for the application
                             form, and showing it implies their status is in doubt. --}}
                        <li><a href="{{ route('contributor.dashboard') }}">Contributor dashboard</a></li>
                        <li><a href="{{ route('contributor.assets.index') }}">Your uploads</a></li>
                        <li><a href="{{ route('contributor.earnings') }}">Earnings</a></li>
                    @else
                        <li><a href="{{ route('contributor.apply') }}">Become a contributor</a></li>
                        <li><a href="{{ route('assets.index') }}">Explore the library</a></li>
                    @endif
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h2>Company</h2>
                <ul class="list-unstyled mb-0" role="list">
                    <li><a href="{{ route('about') }}">About</a></li>
                    <li><a href="{{ route('blog') }}">Blog</a></li>
                    <li><a href="{{ route('careers') }}">Careers</a></li>
                    <li><a href="{{ route('pricing') }}">Pricing</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h2>Support</h2>
                <ul class="list-unstyled mb-0" role="list">
                    <li><a href="mailto:{{ $settings->get('support_email') }}">Contact support</a></li>
                    <li><a href="{{ route('page', 'license') }}">Licence information</a></li>
                    <li><a href="{{ route('page', 'terms') }}">Terms &amp; conditions</a></li>
                    <li><a href="{{ route('page', 'privacy') }}">Privacy policy</a></li>
                    <li><a href="{{ route('page', 'refunds') }}">Refund policy</a></li>
                </ul>
            </div>
        </div>

        <hr class="my-4" style="border-color:var(--ico-border); opacity:1">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <p class="mb-0" style="font-size:.85rem; color:var(--ico-muted)">
                &copy; {{ date('Y') }} {{ $settings->get('site_name', 'Iconors') }}. All rights reserved.
            </p>
            <button class="btn btn-ico-ghost btn-sm" type="button"
                    data-ico-theme-toggle aria-pressed="false">
                <i class="bi bi-circle-half me-1" aria-hidden="true"></i> Theme
            </button>
        </div>
    </div>
</footer>
