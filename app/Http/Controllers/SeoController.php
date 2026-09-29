<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Contributor;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

/**
 * Technical SEO endpoints — brief §29.
 *
 * robots.txt and the sitemaps are generated from live data rather than
 * shipped as static files, so a newly published asset is discoverable
 * without anyone remembering to regenerate anything.
 */
class SeoController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function robots()
    {
        $lines = [];

        // The master indexing switch. A staging site is de-indexed by
        // flipping one setting rather than editing a file on the server.
        if (! $this->settings->get('seo_allow_indexing', true)) {
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';

            return $this->text(implode("\n", $lines));
        }

        $lines[] = 'User-agent: *';
        $lines[] = 'Allow: /';
        $lines[] = '';

        // Anything private, transactional or infinitely paginated.
        foreach ([
            '/admin', '/dashboard', '/contributor', '/download',
            '/login', '/register', '/cart', '/checkout', '/storage/',
        ] as $path) {
            $lines[] = "Disallow: {$path}";
        }

        if ($extra = $this->settings->get('seo_robots_extra')) {
            $lines[] = '';
            $lines[] = trim((string) $extra);
        }

        if ($this->settings->get('seo_sitemap_enabled', true)) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        return $this->text(implode("\n", $lines));
    }

    /** Sitemap index — points at the per-type sitemaps. */
    public function sitemapIndex()
    {
        abort_unless($this->settings->get('seo_sitemap_enabled', true), 404);

        $maps = ['pages'];

        foreach (['assets', 'categories', 'contributors'] as $type) {
            if ($this->settings->get("seo_sitemap_{$type}", true)) {
                $maps[] = $type;
            }
        }

        $xml = view('seo.sitemap-index', [
            'maps'    => $maps,
            'updated' => now(),
        ])->render();

        return $this->xml($xml);
    }

    public function sitemap(string $type)
    {
        abort_unless($this->settings->get('seo_sitemap_enabled', true), 404);
        abort_unless(in_array($type, ['pages', 'assets', 'categories', 'contributors'], true), 404);

        if ($type !== 'pages') {
            abort_unless($this->settings->get("seo_sitemap_{$type}", true), 404);
        }

        // Cached: a large catalogue makes this expensive, and crawlers
        // hit it far more often than the content actually changes.
        $urls = Cache::remember("sitemap.{$type}", 3600, fn () => $this->urlsFor($type));

        return $this->xml(view('seo.sitemap', ['urls' => $urls])->render());
    }

    private function urlsFor(string $type): array
    {
        return match ($type) {
            'pages' => [
                ['loc' => route('home'),          'priority' => '1.0', 'freq' => 'daily'],
                ['loc' => route('assets.index'),  'priority' => '0.9', 'freq' => 'daily'],
                ['loc' => route('assets.index', ['price' => 'free']),    'priority' => '0.8', 'freq' => 'daily'],
                ['loc' => route('assets.index', ['sort' => 'trending']), 'priority' => '0.8', 'freq' => 'daily'],
                ['loc' => route('assets.index', ['sort' => 'newest']),   'priority' => '0.8', 'freq' => 'daily'],
                ['loc' => route('contributor.apply'), 'priority' => '0.5', 'freq' => 'monthly'],
            ],

            // Only published assets. Listing a draft would send crawlers
            // to a 404 and waste crawl budget.
            'assets' => Asset::published()
                ->orderByDesc('published_at')
                ->limit(50000)
                ->get(['slug', 'updated_at'])
                ->map(fn ($a) => [
                    'loc'      => route('assets.show', $a->slug),
                    'lastmod'  => optional($a->updated_at)->toAtomString(),
                    'priority' => '0.7',
                    'freq'     => 'weekly',
                ])->all(),

            'categories' => DB::table('categories')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('assets_count', '>', 0)
                ->get(['slug', 'updated_at'])
                ->map(fn ($c) => [
                    'loc'      => route('assets.index', ['category' => $c->slug]),
                    'lastmod'  => $c->updated_at ? \Carbon\Carbon::parse($c->updated_at)->toAtomString() : null,
                    'priority' => '0.6',
                    'freq'     => 'weekly',
                ])->all(),

            'contributors' => Contributor::where('status', 'approved')
                ->where('assets_count', '>', 0)
                ->get(['slug', 'updated_at'])
                ->map(fn ($c) => [
                    'loc'      => route('assets.index', ['contributor' => $c->slug]),
                    'lastmod'  => optional($c->updated_at)->toAtomString(),
                    'priority' => '0.5',
                    'freq'     => 'weekly',
                ])->all(),

            default => [],
        };
    }

    private function text(string $body)
    {
        return Response::make($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function xml(string $body)
    {
        return Response::make($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
