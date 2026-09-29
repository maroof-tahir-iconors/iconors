<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Contributor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /**
     * Homepage.
     *
     * Nothing here is hard-coded (brief §42) — every section reads from
     * the database. The whole payload is cached for 5 minutes because the
     * homepage is the single most-requested route and none of this data
     * is user-specific.
     */
    public function __invoke()
    {
        $data = Cache::remember('home.sections', 300, function () {
            $withRelations = ['contributor:id,display_name,slug', 'previews'];

            /*
             * The homepage is a shop window, so it shows COLOURFUL work only.
             *
             * A large part of the catalogue is monochrome line art — good
             * artwork, but a grid of black glyphs reads as empty. Colour is
             * measured from the files themselves by `iconors:analyse-colours`,
             * because nothing in the metadata reveals it.
             *
             * Seeded placeholder tiles are excluded too: they are lettered
             * stand-ins, not real assets, and look like a broken import.
             */
            $showcase = fn () => Asset::published()
                ->where('metadata->is_colourful', true)
                ->whereDoesntHave('previews', fn ($q) => $q->where('path', 'like', 'demo/%'))
                ->whereHas('previews')
                ->with($withRelations);

            return [
                'newest' => $showcase()->orderByDesc('published_at')->limit(12)->get(),

                'trending' => $showcase()->orderByDesc('downloads_count')->limit(12)->get(),

                'free' => $showcase()->free()->orderByDesc('downloads_count')->limit(12)->get(),

                'premium' => $showcase()->premium()->orderByDesc('downloads_count')->limit(12)->get(),

                'categories' => Category::whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderByDesc('is_featured')->orderBy('sort_order')
                    ->limit(12)->get(),

                'assetTypes' => AssetType::where('is_active', true)
                    ->orderBy('sort_order')->get(),

                'contributors' => Contributor::where('status', 'approved')
                    ->orderByDesc('downloads_count')->limit(6)->get(),

                /*
                 * Scrolling text tickers.
                 *
                 * Real categories and tags rather than icon tiles: the strip
                 * doubles as navigation and tells a visitor at a glance what
                 * the catalogue actually covers, which a row of anonymous
                 * glyphs cannot.
                 */
                'marqueeTop' => DB::table('categories')
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where('assets_count', '>', 0)
                    ->orderByDesc('assets_count')
                    ->limit(16)
                    ->get(['name', 'slug', 'assets_count'])
                    ->map(fn ($c) => [
                        'label' => $c->name,
                        'count' => $c->assets_count,
                        'icon'  => 'folder2',
                        'url'   => route('assets.index', ['category' => $c->slug]),
                    ])->all(),

                'marqueeBottom' => DB::table('tags')
                    ->where('usage_count', '>', 0)
                    ->orderByDesc('usage_count')
                    ->limit(18)
                    ->get(['name', 'slug'])
                    ->map(fn ($t) => [
                        'label' => Str::title($t->name),
                        'icon'  => 'hash',
                        'url'   => route('assets.index', ['q' => $t->name]),
                    ])->all(),

                'floating' => Asset::published()
                    ->where('metadata->is_colourful', true)
                    ->whereHas('assetType', fn ($q) => $q->where('slug', 'svg-icon'))
                    ->whereDoesntHave('previews', fn ($q) => $q->where('path', 'like', 'demo/%'))
                    ->whereHas('previews')
                    ->with('previews')
                    ->inRandomOrder()
                    ->limit(6)
                    ->get(),

                'stats' => [
                    'assets'       => Asset::published()->count(),
                    'contributors' => Contributor::where('status', 'approved')->count(),
                    'downloads'    => (int) Asset::published()->sum('downloads_count'),
                ],
            ];
        });

        return view('home', $data + ['navTypes' => $data['assetTypes']->take(6)]);
    }
}
