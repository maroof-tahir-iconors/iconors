<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\Format;
use App\Models\License;
use App\Models\Style;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulks out the demo catalogue on top of DemoCatalogueSeeder.
 *
 * Separate from DemoCatalogueSeeder on purpose: that seeder's
 * `updateOrCreate` re-randomises views/downloads/likes on every asset it
 * owns, so re-running it to get more rows would silently reshuffle the
 * stats of the original 60. This one only ever adds.
 *
 * Titles are themed per category rather than dealt round-robin, so the
 * category pages read like a real catalogue ("Pizza Slice" under Food, not
 * under Cryptocurrency) and every category ends up populated.
 *
 * Safe to re-run: keyed on slug, and it regenerates only missing previews.
 */
class ExtraDemoAssetsSeeder extends Seeder
{
    /**
     * Subject nouns per category slug. Each becomes one asset, with the
     * type-appropriate suffix ("Icon" / "Animation" / ...) appended.
     *
     * Split into two constants, and seeded in two passes, for a reason worth
     * keeping: the asset TYPE is picked off a running counter, and the type
     * decides the title suffix, which decides the slug. Merging these into one
     * list would shift the counter for every category after the first, change
     * their titles, and duplicate the already-seeded rows under new names.
     * Pass 1 reproduces the original numbering exactly, so existing rows match
     * by slug and are updated in place; pass 2 continues from there.
     *
     * @var array<string, list<string>>
     */
    private const SUBJECTS = [
        'business' => ['Briefcase', 'Handshake Deal', 'Office Building', 'Presentation Chart', 'Target Goal', 'Team Meeting'],
        'marketing' => ['Megaphone Announcement', 'Conversion Funnel', 'Email Campaign', 'Billboard Advert', 'Influencer Reach', 'Analytics Dashboard'],
        'startups' => ['Rocket Ship', 'Lightbulb Idea', 'Pitch Deck', 'Seed Funding', 'Unicorn Badge', 'Product Blueprint'],
        'technology' => ['Server Rack', 'Cloud Network', 'Circuit Board', 'Fingerprint Scan', 'VR Headset', 'Satellite Dish'],
        'artificial-intelligence' => ['Neural Network', 'Robot Assistant', 'Machine Learning Brain', 'Chatbot Bubble', 'Face Recognition', 'Prompt Sparkle'],
        'software-development' => ['Code Terminal', 'Git Branch', 'Bug Fix', 'API Plug', 'Database Stack', 'Pull Request'],
        'education' => ['School Backpack', 'Chalkboard Lesson', 'Diploma Scroll', 'Microscope Lab', 'Abacus Counting', 'Library Shelf'],
        'e-learning' => ['Online Course', 'Video Lecture', 'Quiz Checklist', 'Certificate Badge', 'Study Timer', 'Virtual Classroom'],
        'health' => ['Stethoscope', 'Heartbeat Monitor', 'Pill Bottle', 'First Aid Kit', 'Vaccine Syringe', 'Mental Wellness'],
        'medical' => ['Hospital Building', 'Ambulance', 'X-Ray Scan', 'DNA Helix', 'Dental Tooth', 'Blood Drop'],
        'fitness' => ['Running Shoe', 'Yoga Pose', 'Water Bottle', 'Weight Scale', 'Jump Rope', 'Treadmill'],
        'finance' => ['Invoice Receipt', 'Stock Chart', 'Tax Calculator', 'Budget Planner', 'Coin Stack', 'Exchange Arrows'],
        'banking' => ['ATM Machine', 'Cheque Book', 'Safe Vault', 'Debit Card', 'Loan Document', 'Money Transfer'],
        'cryptocurrency' => ['Ethereum Coin', 'Blockchain Cube', 'Crypto Wallet', 'Mining Rig', 'NFT Frame', 'Token Swap'],
        'food' => ['Pizza Slice', 'Burger Meal', 'Sushi Roll', 'Bread Loaf', 'Salad Bowl', 'Ice Cream Cone'],
        'beverages' => ['Tea Cup', 'Smoothie Glass', 'Wine Bottle', 'Beer Mug', 'Juice Carton', 'Cocktail Glass'],
        'travel' => ['Suitcase Luggage', 'Passport Stamp', 'Beach Umbrella', 'Mountain Trail', 'World Globe', 'Boarding Pass'],
        'social-media' => ['Like Thumb', 'Share Arrow', 'Hashtag Symbol', 'Story Circle', 'Follower Count', 'Comment Thread'],
        'e-commerce' => ['Checkout Basket', 'Delivery Truck', 'Return Package', 'Product Box', 'Discount Coupon', 'Wishlist Star'],
        'shopping' => ['Price Tag', 'Store Front', 'Barcode Scan', 'Shopping List', 'Gift Wrap', 'Loyalty Card'],
        'nature' => ['Mountain Peak', 'Ocean Wave', 'Desert Cactus', 'Flower Bloom', 'Butterfly', 'Waterfall'],
        'weather' => ['Thunderstorm', 'Snowflake', 'Rainbow Arc', 'Tornado Funnel', 'Fog Cloud', 'Humidity Drop'],
        'sports' => ['Tennis Racket', 'Cricket Bat', 'Swimming Pool', 'Cycling Helmet', 'Boxing Glove', 'Skateboard'],
        'music' => ['Acoustic Guitar', 'Piano Keys', 'Drum Kit', 'Microphone Stand', 'Vinyl Record', 'Equaliser Bars'],
    ];

    /**
     * Second pass — depth for every category. See the note on SUBJECTS for
     * why these are kept separate rather than appended to the list above.
     *
     * @var array<string, list<string>>
     */
    private const MORE_SUBJECTS = [
        'business' => ['Growth Arrow', 'Contract Signing', 'Corporate Ladder', 'Business Card', 'Boardroom Table', 'Strategy Chess', 'Partnership Bridge', 'Revenue Graph', 'Client Portfolio', 'Workflow Diagram', 'Company Badge', 'Merger Handshake'],
        'marketing' => ['Brand Identity', 'Content Calendar', 'Search Ranking', 'Ad Banner', 'Customer Persona', 'Split Test', 'Newsletter Signup', 'Viral Share', 'Promo Coupon', 'Market Research', 'Loyalty Programme', 'Press Release'],
        'startups' => ['Venture Capital', 'Co-founder Duo', 'Runway Chart', 'Prototype Sketch', 'Accelerator Badge', 'Term Sheet', 'Angel Investor', 'Burn Rate', 'Product Launch', 'Whiteboard Session', 'Equity Split', 'Demo Day'],
        'technology' => ['Quantum Processor', 'Data Centre', 'Ethernet Cable', 'Smart Watch', 'Drone Camera', 'Biometric Lock', 'Router Signal', 'Solid State Drive', 'Motherboard', 'Augmented Reality', 'Edge Computing', 'Fibre Optic'],
        'artificial-intelligence' => ['Deep Learning Layers', 'Training Dataset', 'Computer Vision Eye', 'Speech Recognition', 'Model Weights', 'Inference Engine', 'Sentiment Analysis', 'Autonomous Vehicle', 'Generative Art', 'Token Stream', 'Reinforcement Loop', 'AI Ethics Shield'],
        'software-development' => ['Continuous Integration', 'Docker Container', 'Unit Test', 'Code Review', 'Merge Conflict', 'Deployment Pipeline', 'Version Tag', 'Refactor Broom', 'Stack Trace', 'Repository Folder', 'Webhook Link', 'Sprint Board'],
        'education' => ['Graduation Cap', 'Pencil Notebook', 'Globe Geography', 'Science Beaker', 'Ruler Compass', 'Alphabet Blocks', 'School Bus', 'Teacher Desk', 'Exam Paper', 'Bookmark Page', 'Report Card', 'Field Trip'],
        'e-learning' => ['Progress Tracker', 'Discussion Forum', 'Screen Share', 'Lesson Playlist', 'Homework Upload', 'Live Webinar', 'Learning Path', 'Skill Badge', 'Note Taking', 'Flashcard Deck', 'Tutor Chat', 'Course Rating'],
        'health' => ['Health Checkup', 'Nutrition Plate', 'Sleep Tracker', 'Meditation Lotus', 'Hand Sanitiser', 'Thermometer', 'Wheelchair Access', 'Prescription Note', 'Immunity Shield', 'Hydration Glass', 'Breathing Lungs', 'Wellness Journal'],
        'medical' => ['Surgery Scalpel', 'Microscope Slide', 'Heart Organ', 'Brain Scan', 'Bone Fracture', 'Intravenous Drip', 'Face Mask', 'Medical Chart', 'Defibrillator', 'Test Tube Sample', 'Eye Examination', 'Pharmacy Cross'],
        'fitness' => ['Kettlebell Swing', 'Protein Shake', 'Step Counter', 'Stretch Band', 'Gym Locker', 'Cycling Spin', 'Press Up', 'Heart Rate Zone', 'Sports Towel', 'Muscle Arm', 'Workout Plan', 'Rest Day'],
        'finance' => ['Profit Margin', 'Balance Sheet', 'Pension Fund', 'Insurance Shield', 'Audit Magnifier', 'Dividend Payout', 'Portfolio Pie', 'Interest Rate', 'Financial Report', 'Currency Symbol', 'Expense Tracker', 'Cash Flow'],
        'banking' => ['Bank Branch', 'Savings Account', 'Mortgage House', 'Credit Score', 'Wire Transfer', 'Deposit Slip', 'Overdraft Alert', 'Banker Desk', 'Currency Note', 'Card Reader', 'Statement Page', 'Vault Key'],
        'cryptocurrency' => ['Bitcoin Coin', 'Smart Contract', 'Cold Storage', 'Decentralised Node', 'Gas Fee', 'Ledger Chain', 'Staking Reward', 'Crypto Exchange', 'Seed Phrase', 'DeFi Protocol', 'Hash Rate', 'Whitepaper Scroll'],
        'food' => ['Pasta Bowl', 'Taco Plate', 'Pancake Stack', 'Cheese Wedge', 'Egg Breakfast', 'Noodle Soup', 'Steak Dinner', 'Glazed Donut', 'Croissant', 'Rice Bowl', 'Sandwich Wrap', 'Chocolate Bar'],
        'beverages' => ['Espresso Shot', 'Milkshake', 'Lemonade Jug', 'Soda Can', 'Mineral Water', 'Champagne Flute', 'Iced Coffee', 'Bubble Tea', 'Whisky Glass', 'Coconut Drink', 'Hot Chocolate', 'Energy Drink'],
        'travel' => ['Airplane Window', 'Hotel Key', 'Trekking Backpack', 'Road Trip Map', 'Cruise Ship', 'Camping Tent', 'Train Journey', 'Tourist Camera', 'Visa Document', 'Souvenir Shop', 'Airport Terminal', 'Travel Insurance'],
        'social-media' => ['Profile Avatar', 'Direct Message', 'Live Stream', 'Short Reel', 'Notification Ping', 'Verified Tick', 'Poll Sticker', 'Emoji Reaction', 'Feed Scroll', 'Group Community', 'Trending Topic', 'Content Creator'],
        'e-commerce' => ['Add To Cart', 'Order Tracking', 'Payment Gateway', 'Product Review', 'Inventory Shelf', 'Flash Sale', 'Free Shipping', 'Invoice Slip', 'Warehouse Box', 'Refund Arrow', 'Online Storefront', 'Buy Now Button'],
        'shopping' => ['Shopping Trolley', 'Mall Directory', 'Sale Sticker', 'Fitting Room', 'Receipt Roll', 'Cashier Till', 'Window Display', 'Bargain Hunt', 'Tote Bag', 'Membership Card', 'Queue Line', 'Self Checkout'],
        'nature' => ['Pine Forest', 'Autumn Leaf', 'River Stream', 'Coral Reef', 'Bird Flight', 'Mushroom Fungi', 'Sunflower Field', 'Volcano', 'Palm Island', 'Bee Pollen', 'Rock Canyon', 'Northern Lights'],
        'weather' => ['Sunny Day', 'Partly Cloudy', 'Heavy Rain', 'Hailstone', 'Wind Gust', 'Heat Wave', 'Frost Ice', 'Barometer', 'Sunrise', 'Sunset', 'Lightning Bolt', 'Weather Forecast'],
        'sports' => ['Football Goal', 'Basketball Hoop', 'Baseball Glove', 'Golf Club', 'Rugby Ball', 'Badminton Shuttle', 'Ice Hockey Stick', 'Table Tennis', 'Volleyball Net', 'Archery Target', 'Marathon Medal', 'Stopwatch Timer'],
        'music' => ['Violin', 'Saxophone', 'Headphone Beat', 'Music Playlist', 'Speaker Amp', 'Sheet Music', 'Brass Trumpet', 'DJ Turntable', 'Metronome', 'Concert Stage', 'Radio Tuner', 'Music Streaming'],
    ];

    /** Preview tile fills, cycled per category so the grid is not monotone. */
    private const TILE_COLOURS = [
        ['#E9EDF7', '#28375A'],
        ['#E4FBEF', '#27AB81'],
        ['#EEE6FF', '#5A01F0'],
        ['#FFF3D6', '#8A6A00'],
        ['#FFE6EC', '#C21540'],
        ['#E2F4FF', '#0B5E8A'],
    ];

    private const SUFFIX_BY_TYPE = [
        'svg-icon' => 'Icon',
        '3d-icon' => '3D Icon',
        'lottie-animation' => 'Animation',
        'animated-icon' => 'Animated Icon',
        'illustration' => 'Illustration',
        'sticker' => 'Sticker',
        'emoji' => 'Emoji',
        'icon-pack' => 'Pack',
    ];

    public function run(): void
    {
        $assetTypes = AssetType::orderBy('sort_order')->get();
        $categories = Category::orderBy('id')->get();
        $styles = Style::orderBy('id')->get();
        $licenses = License::orderBy('id')->get();
        $formats = Format::all()->keyBy('slug');
        $contributors = Contributor::orderBy('id')->get();
        $tags = Tag::all();

        if ($assetTypes->isEmpty() || $categories->isEmpty() || $contributors->isEmpty()) {
            $this->command?->error('Run DemoCatalogueSeeder first — taxonomy and contributors are missing.');

            return;
        }

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

        // Build the work list up front so the counter below walks it in a
        // fixed order: every category's base subjects first, then every
        // category's extra subjects. See the note on SUBJECTS for why the
        // order is load-bearing rather than cosmetic.
        $workByPass = [];

        foreach ([self::SUBJECTS, self::MORE_SUBJECTS] as $passIndex => $bank) {
            $items = [];

            foreach ($categories as $catIndex => $category) {
                $subjects = $bank[$category->slug] ?? null;

                if (! $subjects) {
                    if ($passIndex === 0) {
                        $this->command?->warn("No subjects defined for category “{$category->slug}” — skipped.");
                    }

                    continue;
                }

                foreach ($subjects as $subject) {
                    $items[] = [$catIndex, $category, $subject];
                }
            }

            $workByPass[] = $items;
        }

        $created = 0;
        $n = 0;

        foreach ($workByPass as $pass) {
            foreach ($pass as [$catIndex, $category, $subject]) {
                $n++;

                $assetType = $assetTypes[$n % $assetTypes->count()];
                $style = $styles[$n % $styles->count()];
                $license = $licenses[$n % $licenses->count()];
                $contributor = $contributors[$n % $contributors->count()];

                $suffix = self::SUFFIX_BY_TYPE[$assetType->slug] ?? 'Icon';
                $title = "{$subject} {$suffix}";
                $slug = $this->uniqueSlug($title);

                $isFree = ($n % 5) < 2;              // ~40% free, matching the original mix
                $isPremium = ! $isFree;
                $price = $isPremium ? round(random_int(200, 2500) / 100, 2) : 0;

                $views = random_int(50, 25000);
                $downloads = random_int(0, intdiv($views, 3));

                $isAnimated = in_array($assetType->slug, ['lottie-animation', 'animated-icon'], true);

                $asset = Asset::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'contributor_id' => $contributor->id,
                        'asset_type_id' => $assetType->id,
                        'category_id' => $category->id,
                        'style_id' => $style->id,
                        'license_id' => $license->id,
                        'title' => $title,
                        'description' => "A {$style->name} style {$subject} for {$category->name} projects.",
                        'is_premium' => $isPremium,
                        'price' => $price,
                        'currency' => 'USD',
                        'is_animated' => $isAnimated,
                        'is_3d' => $assetType->slug === '3d-icon',
                        'has_transparency' => true,
                        'status' => 'published',
                        'published_at' => now()
                            ->subDays(random_int(0, 120))
                            ->subHours(random_int(0, 23)),
                        'is_featured' => $n % 12 === 0,
                        'views_count' => $views,
                        'downloads_count' => $downloads,
                        'likes_count' => random_int(0, intdiv($downloads, 2) + 50),
                        'sales_count' => $isPremium ? random_int(0, 300) : 0,
                        'rating_average' => round(random_int(350, 500) / 100, 2),
                        'rating_count' => random_int(0, 150),
                    ]
                );

                if ($asset->wasRecentlyCreated) {
                    $created++;
                }

                if ($tags->isNotEmpty()) {
                    $asset->tags()->sync(
                        $tags->random(min($tags->count(), random_int(3, 6)))->pluck('id')->all()
                    );
                }

                $pool = $formatPoolByType[$assetType->slug] ?? ['svg', 'png'];
                $asset->formats()->sync(
                    collect($pool)
                        ->shuffle()
                        ->take(random_int(1, min(3, count($pool))))
                        ->map(fn (string $s) => $formats->get($s)?->id)
                        ->filter()
                        ->values()
                        ->all()
                );

                $previewPath = "demo/previews/{$slug}.svg";

                AssetPreview::updateOrCreate(
                    ['asset_id' => $asset->id, 'type' => 'medium'],
                    [
                        'disk' => 'public',
                        'path' => $previewPath,
                        'width' => 240,
                        'height' => 240,
                        'size_bytes' => 400,
                        'is_watermarked' => false,
                        'is_primary' => true,
                    ]
                );

                // The DB row is not enough — DemoCatalogueSeeder points at
                // preview files that something else created by hand, so a new
                // asset without this write renders as a broken image.
                if (! Storage::disk('public')->exists($previewPath)) {
                    Storage::disk('public')->put(
                        $previewPath,
                        $this->previewSvg($subject, self::TILE_COLOURS[$catIndex % count(self::TILE_COLOURS)])
                    );
                }

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

        $this->refreshCounts();

        $this->command?->info("Added {$created} new demo assets (".Asset::count().' total).');
    }

    /**
     * Denormalised counters that nothing currently maintains.
     *
     * `categories.assets_count` was 0 for every row, which is why the homepage
     * category grid read "0 assets" even with a full catalogue. Recomputed from
     * the real rows rather than incremented, so it self-heals whatever the
     * previous drift was.
     */
    private function refreshCounts(): void
    {
        DB::statement("
            UPDATE categories c
            SET assets_count = (
                SELECT COUNT(*) FROM assets a
                WHERE a.category_id = c.id
                  AND a.status = 'published'
                  AND a.deleted_at IS NULL
            )
        ");

        DB::statement("
            UPDATE contributors ct
            SET assets_count = (
                SELECT COUNT(*) FROM assets a
                WHERE a.contributor_id = ct.id
                  AND a.status = 'published'
                  AND a.deleted_at IS NULL
            ),
            downloads_count = (
                SELECT COALESCE(SUM(a.downloads_count), 0) FROM assets a
                WHERE a.contributor_id = ct.id
                  AND a.deleted_at IS NULL
            )
        ");
    }

    /** Slugs are unique table-wide, so avoid colliding with the original 60. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'asset';
        $slug = $base;
        $suffix = 2;

        while (Asset::withTrashed()->where('slug', $slug)->exists()) {
            // An existing row for THIS title is fine — that is the re-run case,
            // and updateOrCreate should match it rather than make a duplicate.
            if (Asset::withTrashed()->where('slug', $slug)->value('title') === $title) {
                return $slug;
            }

            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Placeholder tile in the same shape as the existing demo previews: a
     * rounded square with the subject's initial, tinted per category.
     */
    private function previewSvg(string $subject, array $colours): string
    {
        [$bg, $fg] = $colours;
        $letter = Str::upper(Str::substr($subject, 0, 1));

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="240" height="240">
          <rect width="240" height="240" rx="48" fill="{$bg}"/>
          <text x="120" y="152" font-family="Inter,system-ui,sans-serif" font-size="110"
                font-weight="700" fill="{$fg}" text-anchor="middle">{$letter}</text>
        </svg>
        SVG;
    }
}
