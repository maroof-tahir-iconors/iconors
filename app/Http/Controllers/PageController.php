<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Contributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Page;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public marketing and content pages — brief §5.
 *
 * Pricing reads plans straight from the database (§22: nothing about
 * pricing is hard-coded), and the editorial pages come from the `pages`
 * table so an administrator can rewrite them without a deploy.
 */
class PageController extends Controller
{
    public function pricing(SettingsService $settings)
    {
        $plans = Plan::with('features')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();

        return view('pages.pricing', [
            'plans'    => $plans,
            'licenses' => License::where('is_active', true)->orderBy('sort_order')->get(),
            'currency' => $settings->get('default_currency', 'USD'),
            'refundDays' => (int) $settings->get('refund_window_days', 14),
            'faqs'     => $this->pricingFaqs($settings),
            'navTypes' => $this->navTypes(),
        ]);
    }

    public function about(SettingsService $settings)
    {
        return view('pages.about', [
            'page'  => Page::where('slug', 'about')->where('status', 'published')->first(),
            'stats' => Cache::remember('about.stats', 600, fn () => [
                'assets'       => Asset::published()->count(),
                'contributors' => Contributor::where('status', 'approved')->count(),
                'downloads'    => (int) DB::table('downloads')->count(),
                'categories'   => DB::table('categories')->whereNull('deleted_at')->count(),
            ]),
            'showcase' => $this->showcase(),
            'navTypes' => $this->navTypes(),
        ]);
    }

    public function careers()
    {
        return view('pages.careers', [
            'page'     => Page::where('slug', 'careers')->where('status', 'published')->first(),
            'showcase' => $this->showcase(10),
            'navTypes' => $this->navTypes(),
        ]);
    }

    /**
     * A handful of real catalogue assets used as page decoration.
     *
     * Deliberately our own inventory rather than external stock imagery:
     * it is on-brand, carries no third-party licensing question, adds no
     * outbound dependency, and doubles as a genuine sample of what the
     * marketplace actually contains.
     */
    private function showcase(int $limit = 14)
    {
        return Cache::remember("showcase.{$limit}", 600, fn () => Asset::published()
            ->whereHas('previews')
            ->with('previews')
            ->whereHas('assetType', fn ($q) => $q->where('slug', 'svg-icon'))
            ->inRandomOrder()
            ->limit($limit)
            ->get());
    }

    /** Generic CMS page — terms, privacy, licence, refunds and so on. */
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('pages.show', ['page' => $page, 'navTypes' => $this->navTypes()]);
    }

    public function blog(Request $request)
    {
        $posts = Post::with('author:id,name')
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas(
                'blogCategory', fn ($c) => $c->where('slug', $slug)
            ))
            ->when($request->query('q'), fn ($q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('pages.blog', [
            'posts'      => $posts,
            'categories' => DB::table('blog_categories')->orderBy('sort_order')->get(),
            'navTypes'   => $this->navTypes(),
        ]);
    }

    public function post(string $slug)
    {
        $post = Post::with('author:id,name', 'blogCategory')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        DB::table('posts')->where('id', $post->id)->increment('views_count');

        return view('pages.post', [
            'post'     => $post,
            'related'  => Post::where('status', 'published')
                ->where('id', '!=', $post->id)
                ->where('blog_category_id', $post->blog_category_id)
                ->latest('published_at')->limit(3)->get(),
            'navTypes' => $this->navTypes(),
        ]);
    }

    /**
     * AEO-friendly question/answer pairs (brief §29). Answers are derived
     * from live settings, so the page cannot drift from the real policy.
     */
    private function pricingFaqs(SettingsService $settings): array
    {
        $limit = (int) $settings->get('free_download_limit_daily', 10);

        return [
            [
                'q' => 'Is there a free plan?',
                'a' => 'Yes. A free account can download free assets — up to '
                    .($limit > 0 ? "{$limit} per day" : 'an unlimited number').' — with no card required.',
            ],
            [
                'q' => 'What is the difference between free and premium assets?',
                'a' => 'Free assets are downloadable on any account. Premium assets need either '
                    .'a one-off purchase or an active subscription, and they include the full source files.',
            ],
            [
                'q' => 'Can I use Iconors assets in commercial work?',
                'a' => 'Yes, under the licence attached to each asset. The licence terms are shown '
                    .'on every asset page before you download.',
            ],
            [
                'q' => 'Can I cancel at any time?',
                'a' => 'Yes. Cancelling stops the next renewal and you keep access until the end of '
                    .'the period you have already paid for.',
            ],
            [
                'q' => 'Do you offer refunds?',
                'a' => 'Refunds can be requested within '.(int) $settings->get('refund_window_days', 14)
                    .' days of purchase.',
            ],
        ];
    }

    private function navTypes()
    {
        return \App\Models\AssetType::where('is_active', true)->orderBy('sort_order')->limit(6)->get();
    }
}
