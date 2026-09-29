<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Style;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    /**
     * Marketplace browse + search.
     *
     * Every filter reads from the query string and is echoed back into
     * pagination links, so any filtered view is a shareable URL — brief §9.
     */
    public function index(Request $request)
    {
        $query = Asset::query()
            ->published()
            // Eager-loaded to keep the grid at a fixed query count rather
            // than N+1 per tile.
            ->with(['contributor:id,display_name,slug', 'previews']);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request->string('sort')->toString());

        $assets = $query->paginate(36)->withQueryString();

        $this->recordSearch($request, $assets->total());

        return view('assets.index', [
            'assets'     => $assets,
            'assetTypes' => AssetType::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => Category::whereNull('parent_id')->where('is_active', true)
                                ->orderBy('sort_order')->get(),
            'styles'     => Style::where('is_active', true)->orderBy('sort_order')->get(),
            'navTypes'   => $this->navTypes(),
        ]);
    }

    public function show(string $slug)
    {
        $asset = Asset::published()
            ->with([
                'contributor', 'assetType', 'category', 'style', 'license',
                'previews', 'tags', 'formats', 'softwares',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        DB::table('assets')->where('id', $asset->id)->increment('views_count');

        $related = Asset::published()
            ->with(['contributor:id,display_name,slug', 'previews'])
            ->where('id', '!=', $asset->id)
            ->where(function ($q) use ($asset) {
                $q->where('category_id', $asset->category_id)
                  ->orWhere('asset_type_id', $asset->asset_type_id);
            })
            ->limit(12)
            ->get();

        return view('assets.show', [
            'asset'    => $asset,
            'related'  => $related,
            'navTypes' => $this->navTypes(),
        ]);
    }

    /** AJAX typeahead. Returns titles and tags, capped and escaped client-side. */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        // Substring match, but prefix hits rank first. Prefix-only misses
        // the common case ("cart" should find "Shopping Cart Icon"), while
        // substring-only buries the most likely intent.
        $escaped = addcslashes($q, '%_\\');

        $suggestions = Asset::published()
            ->where('title', 'like', "%{$escaped}%")
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ["{$escaped}%"])
            ->orderByDesc('downloads_count')
            ->limit(8)
            ->get(['title', 'slug'])
            ->map(fn ($a) => [
                'label' => $a->title,
                'url'   => route('assets.show', $a->slug),
            ]);

        return response()->json(['suggestions' => $suggestions]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($term = trim((string) $request->query('q'))) {
            // Grouped so the OR cannot escape the surrounding published()
            // constraint — without the closure this would leak drafts.
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%")
                  ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', "%{$term}%"));
            });
        }

        if ($type = $request->query('type')) {
            $query->whereHas('assetType', fn ($q) => $q->where('slug', $type));
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($style = $request->query('style')) {
            $query->whereHas('style', fn ($q) => $q->where('slug', $style));
        }

        match ($request->query('price')) {
            'free'    => $query->where('is_premium', false),
            'premium' => $query->where('is_premium', true),
            default   => null,
        };

        if ($request->query('animated') === '1') {
            $query->where('is_animated', true);
        }

        if ($request->query('threed') === '1') {
            $query->where('is_3d', true);
        }

        if ($contributor = $request->query('contributor')) {
            $query->whereHas('contributor', fn ($q) => $q->where('slug', $contributor));
        }
    }

    /**
     * Whitelisted sorts only. Interpolating a user-supplied column into
     * orderBy is a SQL injection vector, so unknown values fall through
     * to the default rather than being passed to the query builder.
     */
    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'newest'     => $query->orderByDesc('published_at'),
            'oldest'     => $query->orderBy('published_at'),
            'downloads'  => $query->orderByDesc('downloads_count'),
            'views'      => $query->orderByDesc('views_count'),
            'likes'      => $query->orderByDesc('likes_count'),
            'rating'     => $query->orderByDesc('rating_average'),
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'trending'   => $query->orderByDesc('downloads_count')->orderByDesc('published_at'),
            default      => $query->orderByDesc('published_at'),
        };
    }

    /**
     * Zero-result searches are the highest-signal input to the catalogue
     * roadmap, so they are flagged rather than merely counted.
     */
    private function recordSearch(Request $request, int $total): void
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return;
        }

        DB::table('search_queries')->insert([
            'query'          => mb_substr($term, 0, 300),
            'user_id'        => $request->user()?->id,
            'results_count'  => $total,
            'is_zero_result' => $total === 0,
            'filters'        => json_encode($request->except(['q', 'page'])),
            'created_at'     => now(),
        ]);
    }

    private function navTypes()
    {
        return AssetType::where('is_active', true)->orderBy('sort_order')->limit(6)->get();
    }
}
