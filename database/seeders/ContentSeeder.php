<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Default editorial content — brief §5, §25.
 *
 * These are real, editable rows rather than hard-coded Blade copy, so an
 * administrator can rewrite any of it. Legal pages are marked `is_system`
 * so the CMS can refuse to delete them.
 *
 * The legal wording here is a STARTING POINT, not legal advice — it needs
 * a solicitor's review before launch (see docs/HANDOVER.md §1.2).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page + ['status' => 'published', 'published_at' => now()],
            );
        }

        $categories = [];

        foreach ([
            ['name' => 'Design',    'description' => 'Craft, process and visual thinking.'],
            ['name' => 'Product',   'description' => 'Building and shipping product.'],
            ['name' => 'Tutorials', 'description' => 'Practical, step-by-step guides.'],
            ['name' => 'Company',   'description' => 'News from the Iconors team.'],
        ] as $i => $category) {
            $categories[$category['name']] = BlogCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                $category + ['sort_order' => $i],
            )->id;
        }

        $author = User::where('email', 'admin@iconors.test')->first() ?? User::first();

        foreach ($this->posts() as $i => $post) {
            Post::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category']] ?? null,
                    'author_id'        => $author?->id,
                    'title'            => $post['title'],
                    'excerpt'          => $post['excerpt'],
                    'content'          => $post['content'],
                    'status'           => 'published',
                    'published_at'     => now()->subDays(($i + 1) * 4),
                    'reading_minutes'  => max(1, (int) round(str_word_count(strip_tags($post['content'])) / 200)),
                ],
            );
        }
    }

    private function pages(): array
    {
        return [
            [
                'slug'      => 'about',
                'title'     => 'About Iconors',
                'is_system' => false,
                'content'   => <<<'HTML'
<p class="lead">Iconors is a marketplace for the small visual pieces that make products feel finished — icons, animations, illustrations, stickers and emojis.</p>
<h2>Why we built it</h2>
<p>Finding a single icon that matches the twelve you already have is harder than it should be. Most libraries give you one style and leave you to improvise the rest. We wanted a place where a set holds together, where the licence is legible before you download, and where the designer who drew it gets paid properly.</p>
<h2>How it works</h2>
<p>Independent designers publish their work here. Every submission is reviewed by a human before it appears. Customers download under a clear licence, and contributors earn a share of every sale and subscription download.</p>
<h2>What we care about</h2>
<ul>
  <li><strong>Clarity over cleverness.</strong> You should know what you are allowed to do with an asset before you download it, not after.</li>
  <li><strong>Paying creators fairly.</strong> Royalty rates are published, and the rate that applied when you sold something never changes retroactively.</li>
  <li><strong>Speed.</strong> A marketplace you have to wait for is a marketplace you stop using.</li>
</ul>
HTML,
            ],
            [
                'slug'      => 'careers',
                'title'     => 'Careers at Iconors',
                'is_system' => false,
                'content'   => <<<'HTML'
<p class="lead">We are a small team building a marketplace used by designers and developers every day.</p>
<h2>How we work</h2>
<ul>
  <li>Remote-first, with deliberate overlap hours rather than fixed ones.</li>
  <li>Small teams that own a whole surface end to end.</li>
  <li>We write things down. Decisions live in documents, not in someone's memory.</li>
</ul>
<h2>Open roles</h2>
<p>We are not actively hiring right now. If you think you would be a strong fit anyway, send us your work — we read everything.</p>
<h2>What we look for</h2>
<p>Craft, and the judgement to know when something is finished. We would rather see one project you can talk about in depth than ten you shipped and forgot.</p>
HTML,
            ],
            [
                'slug'      => 'license',
                'title'     => 'Licence Information',
                'is_system' => true,
                'content'   => <<<'HTML'
<p class="lead">Every asset on Iconors carries a licence. The exact terms are shown on the asset page before you download.</p>
<h2>Standard licence</h2>
<p>Use in unlimited personal and commercial projects. You may modify the asset. You may not redistribute or resell the source file, either on its own or as part of a competing asset library.</p>
<h2>Extended licence</h2>
<p>Everything in the standard licence, plus use in products offered for resale — templates, themes and merchandise.</p>
<h2>What never changes</h2>
<p>A licence is issued at the moment you download. Every download stores a snapshot of the terms as they were at that moment, so editing the wording here never alters a licence already granted to you.</p>
<p class="text-muted"><em>This summary is for orientation. The full licence text attached to each asset is the operative document.</em></p>
HTML,
            ],
            [
                'slug'      => 'terms',
                'title'     => 'Terms & Conditions',
                'is_system' => true,
                'content'   => '<p class="lead">These terms govern your use of Iconors.</p><p>Placeholder terms pending legal review. Do not launch with this text — see docs/HANDOVER.md.</p>',
            ],
            [
                'slug'      => 'privacy',
                'title'     => 'Privacy Policy',
                'is_system' => true,
                'content'   => '<p class="lead">How Iconors handles your data.</p><p>Placeholder policy pending legal review. Do not launch with this text — see docs/HANDOVER.md.</p>',
            ],
            [
                'slug'      => 'refunds',
                'title'     => 'Refund Policy',
                'is_system' => true,
                'content'   => '<p class="lead">Refunds can be requested within the window shown at checkout.</p><p>Placeholder policy pending legal review.</p>',
            ],
        ];
    }

    private function posts(): array
    {
        return [
            [
                'slug'     => 'choosing-an-icon-style-that-scales',
                'title'    => 'Choosing an icon style that scales with your product',
                'category' => 'Design',
                'excerpt'  => 'Stroke weight, corner radius and optical size decide whether your icon set still works at 16px.',
                'content'  => '<p>Most icon sets look fine in a design file and fall apart in the interface. The usual cause is optical size: a glyph drawn at 48px and scaled to 16px loses the very details that made it readable.</p><h2>Pick a stroke weight and hold it</h2><p>Mixed stroke weights are the fastest way to make a set look borrowed from three places. Decide early and apply it everywhere.</p><h2>Draw for the smallest size you ship</h2><p>If 16px is your smallest, draw at 16 and scale up. The reverse never survives.</p>',
            ],
            [
                'slug'     => 'when-to-use-lottie-instead-of-a-gif',
                'title'    => 'When to use Lottie instead of a GIF',
                'category' => 'Tutorials',
                'excerpt'  => 'Lottie is vector, tiny and controllable. GIFs are none of those. Here is when each still makes sense.',
                'content'  => '<p>A 300KB GIF and a 12KB Lottie can look identical on screen. The difference shows up on a slow connection, on a high-DPI display, and the moment you want to change a colour.</p><h2>Reach for Lottie when</h2><ul><li>The animation is vector artwork</li><li>You need it crisp at any size</li><li>You want to pause, scrub or recolour it at runtime</li></ul><h2>A GIF is still fine when</h2><p>The source is video or raster, or the surface you are embedding into cannot run JavaScript — an email, for instance.</p>',
            ],
            [
                'slug'     => 'how-contributor-royalties-work',
                'title'    => 'How contributor royalties work on Iconors',
                'category' => 'Company',
                'excerpt'  => 'Where the money goes, when it becomes withdrawable, and why rate changes are never retroactive.',
                'content'  => '<p>Every sale and every subscription download credits the contributor who made the asset. Here is the mechanism, in plain terms.</p><h2>The rate is frozen at purchase</h2><p>Each order stores the commission rate that applied at the moment of sale. If the platform rate changes later, what you already earned is untouched.</p><h2>The holding period</h2><p>Earnings stay pending for a set period before they can be withdrawn. That window exists so a refund can be settled before money leaves the platform.</p>',
            ],
            [
                'slug'     => 'building-a-design-system-icon-library',
                'title'    => 'Building an icon library your design system can live with',
                'category' => 'Product',
                'excerpt'  => 'Naming, categories and versioning matter more than the drawings once you pass a hundred icons.',
                'content'  => '<p>The first fifty icons are a drawing problem. The next five hundred are a librarianship problem.</p><h2>Name for search, not for taxonomy</h2><p>People search for what they want to express, not where you filed it. "delete" should find the bin icon even if you called it "trash".</p><h2>Version the set, not the icon</h2><p>Consumers upgrade a library. They do not want to track thirty individual asset versions.</p>',
            ],
        ];
    }
}
