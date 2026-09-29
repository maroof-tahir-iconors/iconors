<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\License;
use App\Models\Style;
use App\Services\Asset\AssetUploadService;
use Illuminate\Http\Request;
use RuntimeException;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetUploadService $uploads,
    ) {}

    public function index(Request $request)
    {
        $contributor = $request->user()->contributor;

        $assets = Asset::where('contributor_id', $contributor->id)
            ->with('previews')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        // Status tab counts, in one grouped query rather than one per tab.
        $counts = Asset::where('contributor_id', $contributor->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('contributor.assets.index', [
            'assets'      => $assets,
            'counts'      => $counts,
            'contributor' => $contributor,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Asset::class);

        return view('contributor.assets.create', [
            'assetTypes' => AssetType::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => Category::whereNull('parent_id')->where('is_active', true)
                                ->orderBy('sort_order')->get(),
            'styles'     => Style::where('is_active', true)->orderBy('sort_order')->get(),
            'licenses'   => License::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Asset::class);

        $validated = $request->validate([
            'file'          => ['required', 'file', 'max:102400'],
            'title'         => ['required', 'string', 'min:3', 'max:200'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'category_id'   => ['nullable', 'exists:categories,id'],
            'style_id'      => ['nullable', 'exists:styles,id'],
            'license_id'    => ['nullable', 'exists:licenses,id'],
            'is_premium'    => ['nullable', 'boolean'],
            'price'         => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'tags'          => ['nullable', 'string', 'max:500'],
            'is_animated'   => ['nullable', 'boolean'],
            'is_3d'         => ['nullable', 'boolean'],
        ]);

        try {
            $asset = $this->uploads->store(
                $request->user()->contributor,
                $request->file('file'),
                $validated,
            );
        } catch (RuntimeException $e) {
            // Upload failures are usually the contributor's to fix (wrong
            // format, unsafe SVG), so the reason is surfaced verbatim
            // rather than replaced with a generic error.
            return back()
                ->withInput()
                ->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('contributor.assets.index')
            ->with('status', "“{$asset->title}” was submitted for review.");
    }
}
