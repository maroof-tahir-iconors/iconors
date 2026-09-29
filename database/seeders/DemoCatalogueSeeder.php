<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Color;
use App\Models\Contributor;
use App\Models\Format;
use App\Models\License;
use App\Models\Software;
use App\Models\Style;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo catalogue so the marketplace has browsable content: taxonomy,
 * contributors and a spread of published assets with previews/files.
 *
 * Every write is keyed on a natural unique key (slug, email, ...) via
 * updateOrCreate/firstOrCreate so this seeder is safe to run repeatedly.
 */
class DemoCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $assetTypes = $this->seedAssetTypes();
        $categories = $this->seedCategories();
        $styles = $this->seedStyles();
        $licenses = $this->seedLicenses();
        $formats = $this->seedFormats();
        $this->seedTags();
        $this->seedSoftware();
        $this->seedColors();
        $contributors = $this->seedContributors();

        $this->seedAssets($assetTypes, $categories, $styles, $licenses, $formats, $contributors);
    }

    /**
     * @return \Illuminate\Support\Collection<int, AssetType>
     */
    private function seedAssetTypes(): \Illuminate\Support\Collection
    {
        $types = [
            [
                'name' => 'SVG Icon',
                'slug' => 'svg-icon',
                'allowed_extensions' => ['svg'],
                'allowed_mimes' => ['image/svg+xml'],
                'sort_order' => 1,
            ],
            [
                'name' => '3D Icon',
                'slug' => '3d-icon',
                'allowed_extensions' => ['glb', 'gltf', 'obj', 'fbx'],
                'allowed_mimes' => ['model/gltf-binary', 'model/gltf+json', 'application/octet-stream'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Lottie Animation',
                'slug' => 'lottie-animation',
                'allowed_extensions' => ['json'],
                'allowed_mimes' => ['application/json'],
                'sort_order' => 3,
            ],
            [
                'name' => 'Animated Icon',
                'slug' => 'animated-icon',
                'allowed_extensions' => ['gif', 'json', 'svg'],
                'allowed_mimes' => ['image/gif', 'application/json', 'image/svg+xml'],
                'sort_order' => 4,
            ],
            [
                'name' => 'Illustration',
                'slug' => 'illustration',
                'allowed_extensions' => ['svg', 'png', 'jpg'],
                'allowed_mimes' => ['image/svg+xml', 'image/png', 'image/jpeg'],
                'sort_order' => 5,
            ],
            [
                'name' => 'Sticker',
                'slug' => 'sticker',
                'allowed_extensions' => ['png', 'webp'],
                'allowed_mimes' => ['image/png', 'image/webp'],
                'sort_order' => 6,
            ],
            [
                'name' => 'Emoji',
                'slug' => 'emoji',
                'allowed_extensions' => ['png', 'svg'],
                'allowed_mimes' => ['image/png', 'image/svg+xml'],
                'sort_order' => 7,
            ],
            [
                'name' => 'Icon Pack',
                'slug' => 'icon-pack',
                'allowed_extensions' => ['zip', 'svg'],
                'allowed_mimes' => ['application/zip', 'image/svg+xml'],
                'sort_order' => 8,
            ],
        ];

        return collect($types)->map(function (array $data) {
            return AssetType::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'allowed_extensions' => $data['allowed_extensions'],
                    'allowed_mimes' => $data['allowed_mimes'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function seedCategories(): \Illuminate\Support\Collection
    {
        $tree = [
            'Business' => ['featured' => true, 'children' => ['Marketing', 'Startups']],
            'Technology' => ['featured' => true, 'children' => ['Artificial Intelligence', 'Software Development']],
            'Education' => ['featured' => false, 'children' => ['E-learning']],
            'Health' => ['featured' => false, 'children' => ['Medical', 'Fitness']],
            'Finance' => ['featured' => true, 'children' => ['Banking', 'Cryptocurrency']],
            'Food' => ['featured' => false, 'children' => ['Beverages']],
            'Travel' => ['featured' => false, 'children' => []],
            'Social Media' => ['featured' => false, 'children' => []],
            'E-commerce' => ['featured' => true, 'children' => ['Shopping']],
            'Nature' => ['featured' => false, 'children' => ['Weather']],
            'Sports' => ['featured' => false, 'children' => []],
            'Music' => ['featured' => false, 'children' => []],
        ];

        $all = collect();
        $sort = 1;

        foreach ($tree as $name => $meta) {
            $parent = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'parent_id' => null,
                    'is_featured' => $meta['featured'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
            $all->push($parent);

            $childSort = 1;
            foreach ($meta['children'] as $childName) {
                $child = Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'is_featured' => false,
                        'is_active' => true,
                        'sort_order' => $childSort++,
                    ]
                );
                $all->push($child);
            }
        }

        return $all;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Style>
     */
    private function seedStyles(): \Illuminate\Support\Collection
    {
        $styles = ['Flat', 'Outline', 'Filled', 'Gradient', 'Isometric', 'Hand-drawn'];

        return collect($styles)->values()->map(function (string $name, int $i) {
            return Style::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, License>
     */
    private function seedLicenses(): \Illuminate\Support\Collection
    {
        $licenses = [
            [
                'name' => 'Free License',
                'slug' => 'free-license',
                'summary' => 'Free for personal and commercial use with attribution.',
                'allows_commercial' => true,
                'allows_modification' => true,
                'allows_redistribution' => false,
                'requires_attribution' => true,
                'is_extended' => false,
            ],
            [
                'name' => 'Premium License',
                'slug' => 'premium-license',
                'summary' => 'Commercial use, no attribution required.',
                'allows_commercial' => true,
                'allows_modification' => true,
                'allows_redistribution' => false,
                'requires_attribution' => false,
                'is_extended' => false,
            ],
            [
                'name' => 'Extended License',
                'slug' => 'extended-license',
                'summary' => 'Extended rights for resale and large-scale distribution.',
                'allows_commercial' => true,
                'allows_modification' => true,
                'allows_redistribution' => true,
                'requires_attribution' => false,
                'is_extended' => true,
            ],
        ];

        return collect($licenses)->values()->map(function (array $data, int $i) {
            return License::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'summary' => $data['summary'],
                    'allows_commercial' => $data['allows_commercial'],
                    'allows_modification' => $data['allows_modification'],
                    'allows_redistribution' => $data['allows_redistribution'],
                    'requires_attribution' => $data['requires_attribution'],
                    'is_extended' => $data['is_extended'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Format>
     */
    private function seedFormats(): \Illuminate\Support\Collection
    {
        $formats = [
            ['name' => 'SVG', 'mime' => 'image/svg+xml', 'editable' => true, 'vector' => true, 'transparency' => true],
            ['name' => 'PNG', 'mime' => 'image/png', 'editable' => false, 'vector' => false, 'transparency' => true],
            ['name' => 'JPG', 'mime' => 'image/jpeg', 'editable' => false, 'vector' => false, 'transparency' => false],
            ['name' => 'WebP', 'mime' => 'image/webp', 'editable' => false, 'vector' => false, 'transparency' => true],
            ['name' => 'GIF', 'mime' => 'image/gif', 'editable' => false, 'vector' => false, 'transparency' => true],
            ['name' => 'JSON', 'mime' => 'application/json', 'editable' => true, 'vector' => true, 'transparency' => true],
            ['name' => 'GLB', 'mime' => 'model/gltf-binary', 'editable' => false, 'vector' => false, 'transparency' => true],
            ['name' => 'EPS', 'mime' => 'application/postscript', 'editable' => true, 'vector' => true, 'transparency' => true],
        ];

        return collect($formats)->values()->map(function (array $data, int $i) {
            return Format::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'mime_type' => $data['mime'],
                    'is_editable' => $data['editable'],
                    'is_vector' => $data['vector'],
                    'supports_transparency' => $data['transparency'],
                    'sort_order' => $i + 1,
                ]
            );
        });
    }

    private function seedTags(): void
    {
        $tags = [
            'icons', 'ui', 'web design', 'mobile app', 'business', 'finance',
            'ecommerce', 'shopping', 'technology', 'artificial intelligence',
            'education', 'healthcare', 'medical', 'food', 'travel',
            'social media', 'music', 'sports', 'nature', 'animals',
            'weather', 'holiday', 'christmas', 'wedding', 'transportation',
            'communication', 'security', 'gaming', 'minimal', 'colorful',
        ];

        foreach ($tags as $name) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => Str::title($name)]
            );
        }
    }

    private function seedSoftware(): void
    {
        $software = ['Figma', 'Sketch', 'Adobe Illustrator', 'Adobe After Effects', 'Blender'];

        foreach ($software as $i => $name) {
            Software::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1]
            );
        }
    }

    private function seedColors(): void
    {
        $colors = [
            ['name' => 'Red', 'hex' => '#EF4444', 'hue' => 0, 'saturation' => 84, 'lightness' => 60],
            ['name' => 'Orange', 'hex' => '#F97316', 'hue' => 25, 'saturation' => 95, 'lightness' => 53],
            ['name' => 'Yellow', 'hex' => '#EAB308', 'hue' => 45, 'saturation' => 93, 'lightness' => 47],
            ['name' => 'Green', 'hex' => '#22C55E', 'hue' => 142, 'saturation' => 71, 'lightness' => 45],
            ['name' => 'Blue', 'hex' => '#3B82F6', 'hue' => 217, 'saturation' => 91, 'lightness' => 60],
            ['name' => 'Purple', 'hex' => '#A855F7', 'hue' => 271, 'saturation' => 91, 'lightness' => 65],
            ['name' => 'Pink', 'hex' => '#EC4899', 'hue' => 330, 'saturation' => 81, 'lightness' => 60],
            ['name' => 'Black', 'hex' => '#000000', 'hue' => 0, 'saturation' => 0, 'lightness' => 0],
            ['name' => 'White', 'hex' => '#FFFFFF', 'hue' => 0, 'saturation' => 0, 'lightness' => 100],
            ['name' => 'Gray', 'hex' => '#6B7280', 'hue' => 220, 'saturation' => 9, 'lightness' => 46],
        ];

        foreach ($colors as $i => $data) {
            Color::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'hex' => $data['hex'],
                    'hue' => $data['hue'],
                    'saturation' => $data['saturation'],
                    'lightness' => $data['lightness'],
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Contributor>
     */
    private function seedContributors(): \Illuminate\Support\Collection
    {
        $contributors = [
            ['name' => 'Alex Ramirez', 'email' => 'contributor1@iconors.test', 'country' => 'US', 'verified' => true, 'bio' => 'Product designer crafting clean, minimal icon sets for SaaS products.'],
            ['name' => 'Sofia Chen', 'email' => 'contributor2@iconors.test', 'country' => 'SG', 'verified' => true, 'bio' => 'Motion designer specializing in Lottie animations and micro-interactions.'],
            ['name' => "Liam O'Connor", 'email' => 'contributor3@iconors.test', 'country' => 'IE', 'verified' => false, 'bio' => 'Illustrator focused on playful, hand-drawn character work.'],
            ['name' => 'Amara Okafor', 'email' => 'contributor4@iconors.test', 'country' => 'NG', 'verified' => true, 'bio' => '3D artist building isometric icon packs for modern web apps.'],
            ['name' => 'Yuki Tanaka', 'email' => 'contributor5@iconors.test', 'country' => 'JP', 'verified' => false, 'bio' => 'UI/UX designer creating pixel-perfect outline icon libraries.'],
            ['name' => 'Elena Petrova', 'email' => 'contributor6@iconors.test', 'country' => 'PL', 'verified' => true, 'bio' => 'Brand illustrator producing vibrant sticker and emoji collections.'],
        ];

        return collect($contributors)->map(function (array $data) {
            $user = User::where('email', $data['email'])->first();

            if (! $user) {
                $user = User::factory()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                ]);
            }

            return Contributor::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'user_id' => $user->id,
                    'display_name' => $data['name'],
                    'biography' => $data['bio'],
                    'country' => $data['country'],
                    'status' => 'approved',
                    'approved_at' => now()->subDays(random_int(30, 400)),
                    'is_verified' => $data['verified'],
                    'is_featured' => false,
                    'assets_count' => 0,
                    'downloads_count' => 0,
                    'followers_count' => random_int(0, 500),
                ]
            );
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AssetType>  $assetTypes
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @param  \Illuminate\Support\Collection<int, Style>  $styles
     * @param  \Illuminate\Support\Collection<int, License>  $licenses
     * @param  \Illuminate\Support\Collection<int, Format>  $formats
     * @param  \Illuminate\Support\Collection<int, Contributor>  $contributors
     */
    private function seedAssets(
        \Illuminate\Support\Collection $assetTypes,
        \Illuminate\Support\Collection $categories,
        \Illuminate\Support\Collection $styles,
        \Illuminate\Support\Collection $licenses,
        \Illuminate\Support\Collection $formats,
        \Illuminate\Support\Collection $contributors
    ): void {
        $titles = [
            'Shopping Cart Icon', 'Rocket Launch Animation', 'Cloud Upload Icon', 'Credit Card Payment Icon',
            'Email Notification Icon', 'Chat Bubble Icon', 'Search Magnifier Icon', 'User Profile Icon',
            'Settings Gear Icon', 'Lock Security Icon', 'Heart Health Icon', 'Medical Cross Icon',
            'Fitness Dumbbell Icon', 'Restaurant Fork Icon', 'Coffee Cup Icon', 'Airplane Travel Icon',
            'Hotel Bed Icon', 'Camera Photo Icon', 'Music Note Icon', 'Headphones Icon',
            'Football Icon', 'Basketball Icon', 'Trophy Award Icon', 'Graduation Cap Icon',
            'Book Reading Icon', 'Calculator Icon', 'Piggy Bank Savings Icon', 'Bitcoin Cryptocurrency Icon',
            'Wallet Icon', 'Bank Building Icon', 'Leaf Nature Icon', 'Tree Forest Icon',
            'Sun Weather Icon', 'Cloud Rain Icon', 'Robot AI Icon', 'Chip Processor Icon',
            'Laptop Computer Icon', 'Smartphone Mobile Icon', 'Wifi Signal Icon', 'Battery Charge Icon',
            'Gift Box Icon', 'Shopping Bag Icon', 'Tag Discount Icon', 'Star Rating Icon',
            'Thumbs Up Icon', 'Like Heart Animation', 'Loading Spinner Animation', 'Confetti Celebration Animation',
            'Rocket Boost Animation', 'Progress Bar Animation', 'Notification Bell Icon', 'Envelope Mail Icon',
            'Calendar Event Icon', 'Clock Time Icon', 'Map Pin Location Icon', 'Compass Navigation Icon',
            'Car Vehicle Icon', 'Bicycle Icon', 'Train Transport Icon', 'Umbrella Icon',
        ];

        $formatPoolByType = [
            'svg-icon' => ['svg', 'png', 'eps'],
            '3d-icon' => ['glb', 'png'],
            'lottie-animation' => ['json', 'gif'],
            'animated-icon' => ['gif', 'json', 'svg'],
            'illustration' => ['svg', 'png', 'jpg'],
            'sticker' => ['png', 'webp'],
            'emoji' => ['png', 'svg'],
            'icon-pack' => ['svg', 'png'],
        ];

        $tags = Tag::all();
        $formatsBySlug = $formats->keyBy('slug');
        $assetTypeCount = $assetTypes->count();
        $categoryCount = $categories->count();
        $styleCount = $styles->count();
        $licenseCount = $licenses->count();
        $contributorCount = $contributors->count();

        foreach ($titles as $i => $title) {
            $assetType = $assetTypes[$i % $assetTypeCount];
            $category = $categories[$i % $categoryCount];
            $style = $styles[$i % $styleCount];
            $license = $licenses[$i % $licenseCount];
            $contributor = $contributors[$i % $contributorCount];

            $slug = Str::slug($title).'-'.($i + 1);

            $isFree = ($i % 5) < 2; // 40% free, 60% premium
            $isPremium = ! $isFree;
            $price = $isPremium ? round(random_int(200, 2500) / 100, 2) : 0;

            $publishedAt = now()
                ->subDays(random_int(0, 90))
                ->subHours(random_int(0, 23))
                ->subMinutes(random_int(0, 59));

            $views = random_int(50, 25000);
            $downloads = random_int(0, intdiv($views, 3));
            $likes = random_int(0, intdiv($downloads, 2) + 50);
            $sales = $isPremium ? random_int(0, 300) : 0;
            $ratingCount = random_int(0, 150);
            $ratingAverage = $ratingCount > 0 ? round(random_int(350, 500) / 100, 2) : 0;

            $isAnimated = in_array($assetType->slug, ['lottie-animation', 'animated-icon'], true);
            $is3d = $assetType->slug === '3d-icon';

            $asset = Asset::updateOrCreate(
                ['slug' => $slug],
                [
                    'contributor_id' => $contributor->id,
                    'asset_type_id' => $assetType->id,
                    'category_id' => $category->id,
                    'style_id' => $style->id,
                    'license_id' => $license->id,
                    'title' => $title,
                    'description' => "A {$style->name} style {$title} crafted for modern digital products.",
                    'is_premium' => $isPremium,
                    'price' => $price,
                    'currency' => 'USD',
                    'is_animated' => $isAnimated,
                    'is_3d' => $is3d,
                    'has_transparency' => true,
                    'status' => 'published',
                    'published_at' => $publishedAt,
                    'is_featured' => $i % 10 === 0,
                    'views_count' => $views,
                    'downloads_count' => $downloads,
                    'likes_count' => $likes,
                    'sales_count' => $sales,
                    'rating_average' => $ratingAverage,
                    'rating_count' => $ratingCount,
                ]
            );

            // Tags: 3-6 random.
            $tagIds = $tags->random(min($tags->count(), random_int(3, 6)))->pluck('id')->all();
            $asset->tags()->sync($tagIds);

            // Formats: 1-3 from the pool appropriate to this asset type.
            $pool = $formatPoolByType[$assetType->slug] ?? ['svg', 'png'];
            $poolCount = count($pool);
            $chosenSlugs = collect($pool)->shuffle()->take(random_int(1, min(3, $poolCount)));
            $formatIds = $chosenSlugs->map(fn (string $slug) => $formatsBySlug->get($slug)?->id)
                ->filter()
                ->values()
                ->all();
            $asset->formats()->sync($formatIds);

            // One primary medium preview (public disk).
            AssetPreview::updateOrCreate(
                ['asset_id' => $asset->id, 'type' => 'medium'],
                [
                    'disk' => 'public',
                    'path' => "demo/previews/{$slug}.svg",
                    'width' => 512,
                    'height' => 512,
                    'size_bytes' => random_int(2000, 40000),
                    'is_watermarked' => false,
                    'is_primary' => true,
                ]
            );

            // One source file (private disk).
            AssetFile::updateOrCreate(
                ['asset_id' => $asset->id, 'is_source' => true],
                [
                    'disk' => 'private',
                    'path' => "demo/sources/{$slug}.svg",
                    'original_name' => "{$slug}.svg",
                    'extension' => 'svg',
                    'mime_type' => 'image/svg+xml',
                    'size_bytes' => random_int(5000, 500000),
                    'requires_purchase' => $isPremium,
                    'scan_status' => 'clean',
                    'scanned_at' => now(),
                ]
            );
        }
    }
}
