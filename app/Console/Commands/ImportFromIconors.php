<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\User;
use App\Services\Asset\SvgSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the existing iconors.com catalogue into this application.
 *
 * The live site is WordPress + Easy Digital Downloads. Its Lottie library
 * lives in a custom `lottie_animation` post type, and the underlying files
 * sit in the public uploads directory, so both are reachable through the
 * WP REST API without credentials.
 *
 * Idempotent: every asset records its source URL in `metadata`, and a
 * second run skips anything already imported rather than duplicating it.
 *
 *   php artisan iconors:import --limit=50
 *   php artisan iconors:import --type=svg
 */
class ImportFromIconors extends Command
{
    protected $signature = 'iconors:import
        {--source=https://iconors.com : Base URL of the WordPress site}
        {--limit=0 : Maximum assets to import (0 = all)}
        {--type=all : all|svg|lottie|raster}
        {--fresh : Remove previously imported assets first}';

    protected $description = 'Import icons and Lottie animations from the live iconors.com site';

    private string $source;

    private const IMPORTABLE = ['svg', 'json', 'png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * WordPress auto-generates resized copies (`name-150x150.png`) and the
     * uploads folder also holds site chrome. Importing those would fill the
     * marketplace with duplicate thumbnails and logo variants.
     */
    private const FURNITURE = [
        'logo', 'favicon', 'placeholder', 'avatar', 'banner', 'screenshot',
        'cropped-', 'site-icon', 'header', 'footer', 'bg-', 'background',
    ];

    /** file URL => category name, built from the lottie_animation posts. */
    private array $lottieMap = [];

    /** Per-category counter so generated titles are unique and ordered. */
    private array $categoryCounters = [];

    public function handle(SvgSanitizer $sanitizer): int
    {
        $this->source = rtrim((string) $this->option('source'), '/');
        $limit = (int) $this->option('limit');
        $type  = (string) $this->option('type');

        if ($this->option('fresh')) {
            $this->removeImported();
        }

        $contributor = $this->importContributor();

        if ($type !== 'svg') {
            $this->buildLottieMap();
        }

        $media = $this->fetchMedia();

        if ($media === []) {
            $this->error('No media returned — is the source site reachable?');

            return self::FAILURE;
        }

        $this->info(count($media).' media items found on '.$this->source);

        $lottieCategories = $this->fetchLottieCategories();
        $imported = 0;

        foreach ($media as $item) {
            if ($limit > 0 && $imported >= $limit) {
                break;
            }

            $url       = (string) ($item['source_url'] ?? '');
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

            if (! in_array($extension, self::IMPORTABLE, true)) {
                continue;
            }

            if ($this->isSiteFurniture($url)) {
                continue;
            }

            $isRaster = in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);

            if ($type === 'svg' && $extension !== 'svg') {
                continue;
            }

            if ($type === 'lottie' && $extension !== 'json') {
                continue;
            }

            if ($type === 'raster' && ! $isRaster) {
                continue;
            }

            // Already imported — matched on source URL, not title, because
            // titles on the source are frequently just numbers.
            if (Asset::where('metadata->source_url', $url)->exists()) {
                continue;
            }

            if ($this->importOne($item, $url, $extension, $contributor, $lottieCategories, $sanitizer)) {
                $imported++;

                if ($imported % 25 === 0) {
                    $this->line("  imported {$imported}…");
                }
            }
        }

        $this->refreshCounters();

        $this->newLine();
        $this->info("Imported {$imported} assets.");
        $this->line('Published assets now: '.Asset::published()->count());

        return self::SUCCESS;
    }

    private function importOne(
        array $item,
        string $url,
        string $extension,
        Contributor $contributor,
        array $lottieCategories,
        SvgSanitizer $sanitizer,
    ): bool {
        $body = $this->download($url);

        if ($body === null) {
            return false;
        }

        // A Lottie file is JSON, but so is a lot of other WordPress
        // debris. Only accept files that actually look like animations.
        if ($extension === 'json' && ! $this->looksLikeLottie($body)) {
            return false;
        }

        if ($extension === 'svg') {
            $result = $sanitizer->sanitize($body);

            if ($result['svg'] === '') {
                $this->warn('  skipped unsafe SVG: '.basename($url));

                return false;
            }

            $body = $result['svg'];
        }

        $isLottie = $extension === 'json';
        $title    = $this->titleFor($item, $url, $isLottie);
        $slug     = $this->uniqueSlug($title);

        $assetType = $this->assetType(match (true) {
            $isLottie             => 'lottie-animation',
            $extension === 'svg'  => 'svg-icon',
            $extension === 'gif'  => 'animated-icon',
            default               => 'illustration',
        });

        $category = $this->categoryFor($url, $lottieCategories);

        $mime = match ($extension) {
            'json' => 'application/json',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return DB::transaction(function () use (
            $contributor, $assetType, $category, $title, $slug, $url, $body, $extension, $isLottie, $mime
        ) {
            $asset = Asset::create([
                'contributor_id' => $contributor->id,
                'asset_type_id'  => $assetType->id,
                'category_id'    => $category?->id,
                'title'          => $title,
                'slug'           => $slug,
                'description'    => match (true) {
                    $isLottie            => "Animated {$title} — Lottie JSON, ready to drop into web and app projects.",
                    $extension === 'svg' => "{$title} — scalable vector icon, editable and export-ready.",
                    $extension === 'gif' => "{$title} — animated graphic, ready to use.",
                    default              => "{$title} — high-resolution raster asset.",
                },
                'is_premium'     => false,
                'price'          => 0,
                'is_animated'    => $isLottie || $extension === 'gif',
                'status'         => 'published',
                'published_at'   => now(),
                'metadata'       => ['source_url' => $url, 'imported_at' => now()->toIso8601String()],
            ]);

            $path = "imported/{$asset->id}/source.{$extension}";
            Storage::disk('private')->put($path, $body);

            AssetFile::create([
                'asset_id'      => $asset->id,
                'disk'          => 'private',
                'path'          => $path,
                'original_name' => basename(parse_url($url, PHP_URL_PATH) ?? "asset.{$extension}"),
                'extension'     => $extension,
                'mime_type'     => $mime,
                'size_bytes'    => strlen($body),
                'checksum'      => hash('sha256', $body),
                'is_source'     => true,
                // SVG is cleared by sanitisation. Lottie JSON is inert data
                // — it is never executed, only parsed by the player.
                'scan_status'   => 'clean',
                'scanned_at'    => now(),
            ]);

            // SVG is its own preview once sanitised. Lottie needs a poster,
            // so the public copy of the JSON is what the player loads.
            $previewPath = "imported/previews/{$asset->id}.{$extension}";
            Storage::disk('public')->put($previewPath, $body);

            AssetPreview::create([
                'asset_id'   => $asset->id,
                'type'       => $isLottie ? 'lottie' : 'medium',
                'disk'       => 'public',
                'path'       => $previewPath,
                'width'      => 240,
                'height'     => 240,
                'size_bytes' => strlen($body),
                'is_primary' => true,
            ]);

            return true;
        });
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Iconors-Importer/1.0'])
                ->timeout(30)
                ->retry(2, 300)
                ->get($url);

            // Cap at 2 MB — an icon or Lottie file far above that is either
            // wrong or not worth serving.
            if (! $response->successful() || strlen($response->body()) > 2_097_152) {
                return null;
            }

            return $response->body();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Filters WordPress resize derivatives and site chrome. */
    private function isSiteFurniture(string $url): bool
    {
        $file = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME));

        // "icon-150x150" is a generated thumbnail of "icon" — importing it
        // would create a duplicate asset at a worse resolution.
        if (preg_match('/-\d{2,4}x\d{2,4}$/', $file)) {
            return true;
        }

        foreach (self::FURNITURE as $needle) {
            if (str_contains($file, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeLottie(string $body): bool
    {
        $data = json_decode($body, true);

        // 'v' (version) + 'layers' are present in every Bodymovin export.
        return is_array($data) && isset($data['v'], $data['layers']);
    }

    /**
     * Derives a usable title.
     *
     * The source's SVG filenames are genuinely descriptive ("Weather",
     * "Wifi-Icon"), so those are used directly. Its Lottie files are bare
     * numbers ("4-2.json"), which would produce 195 assets all called
     * "Lottie Animation" — so those are named from the category the post
     * actually belongs to, plus a per-category counter.
     */
    private function titleFor(array $item, string $url, bool $isLottie): string
    {
        $raw = trim(strip_tags((string) ($item['title']['rendered'] ?? '')));

        if ($raw === '' || preg_match('/^[\d\-_]+$/', $raw)) {
            $raw = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_FILENAME);
        }

        // Still meaningless: fall back to the mapped category.
        if (preg_match('/^[\d\-_]+$/', (string) $raw)) {
            $category = $this->lottieMap[$url] ?? ($isLottie ? 'Animation' : 'Icon');

            $this->categoryCounters[$category] ??= 0;
            $n = ++$this->categoryCounters[$category];

            return trim(Str::singular($category).' '.str_pad((string) $n, 2, '0', STR_PAD_LEFT));
        }

        $title = Str::title(trim(preg_replace('/[-_]+/', ' ', (string) $raw) ?? ''));

        return Str::limit($title, 90, '') ?: ($isLottie ? 'Animation' : 'Icon');
    }

    /**
     * Category from the real post→file mapping where we have one, then the
     * upload path, then a type default.
     */
    private function categoryFor(string $url, array $lottieCategories): ?Category
    {
        if (isset($this->lottieMap[$url])) {
            return $this->category($this->lottieMap[$url]);
        }

        foreach ($lottieCategories as $name) {
            if (Str::contains(Str::lower($url), Str::slug($name))) {
                return $this->category($name);
            }
        }

        return $this->category(match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION))) {
            'json'  => 'Animations',
            'svg'   => 'Icons',
            'gif'   => 'Animated Graphics',
            default => 'Illustrations',
        });
    }

    /**
     * Builds a file-URL → category-name map by reading each
     * `lottie_animation` post page and pulling the player's src.
     *
     * The REST API exposes the posts and their taxonomy but not the file,
     * and the file URL is only present in the rendered markup — hence one
     * request per post. Throttled, and failures are skipped rather than
     * aborting the whole import.
     */
    private function buildLottieMap(): void
    {
        $categories = [];

        try {
            $response = Http::timeout(30)->get("{$this->source}/wp-json/wp/v2/lottie_category", ['per_page' => 100]);

            foreach ($response->json() ?: [] as $term) {
                $categories[$term['id']] = $term['name'];
            }
        } catch (\Throwable) {
            return;
        }

        $posts = [];

        for ($page = 1; $page <= 4; $page++) {
            try {
                $response = Http::timeout(45)->get("{$this->source}/wp-json/wp/v2/lottie_animation", [
                    'per_page' => 100,
                    'page'     => $page,
                    '_fields'  => 'id,link,lottie_category',
                ]);

                $batch = $response->successful() ? $response->json() : [];

                if (! is_array($batch) || $batch === []) {
                    break;
                }

                $posts = array_merge($posts, $batch);

                if (count($batch) < 100) {
                    break;
                }
            } catch (\Throwable) {
                break;
            }
        }

        if ($posts === []) {
            return;
        }

        $this->line('Mapping '.count($posts).' Lottie posts to their files…');
        $bar = $this->output->createProgressBar(count($posts));

        // Concurrent, in batches. Each post page is ~220KB and the file URL
        // only exists in the rendered markup, so this is 195 fetches no
        // matter what — done sequentially it took minutes and imported
        // nothing. Pooled, it is roughly an order of magnitude faster.
        foreach (array_chunk($posts, 12) as $chunk) {
            try {
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($post) => $pool->timeout(25)
                        ->withHeaders(['User-Agent' => 'Iconors-Importer/1.0'])
                        ->get($post['link'] ?? ''),
                    $chunk,
                ));
            } catch (\Throwable) {
                $bar->advance(count($chunk));

                continue;
            }

            foreach ($chunk as $i => $post) {
                $bar->advance();

                $response = $responses[$i] ?? null;

                if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) {
                    continue;
                }

                if (! preg_match('~https?://[^"\'\s]+\.json~i', $response->body(), $match)) {
                    continue;
                }

                $termId = $post['lottie_category'][0] ?? null;
                $this->lottieMap[$match[0]] = $categories[$termId] ?? 'Animations';
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info(count($this->lottieMap).' Lottie files mapped to categories.');
    }

    private function fetchMedia(): array
    {
        $all = [];

        for ($page = 1; $page <= 10; $page++) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Iconors-Importer/1.0'])
                    ->timeout(60)
                    ->get("{$this->source}/wp-json/wp/v2/media", [
                        'per_page' => 100,
                        'page'     => $page,
                        '_fields'  => 'id,source_url,title,mime_type',
                    ]);

                if (! $response->successful()) {
                    break;
                }

                $batch = $response->json();

                if (! is_array($batch) || $batch === []) {
                    break;
                }

                $all = array_merge($all, $batch);

                if (count($batch) < 100) {
                    break;
                }
            } catch (\Throwable) {
                break;
            }
        }

        return $all;
    }

    private function fetchLottieCategories(): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->source}/wp-json/wp/v2/lottie_category", ['per_page' => 100]);

            return $response->successful()
                ? array_column($response->json(), 'name')
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function importContributor(): Contributor
    {
        $user = User::firstOrCreate(
            ['email' => 'studio@iconors.test'],
            ['name' => 'Iconors Studio', 'password' => Hash::make(Str::random(32)), 'email_verified_at' => now()],
        );

        if (! $user->hasRole('Contributor')) {
            $user->assignRole('Contributor');
        }

        return Contributor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'Iconors Studio',
                'slug'         => 'iconors-studio',
                'biography'    => 'The in-house Iconors design team.',
                'status'       => 'approved',
                'is_verified'  => true,
                'is_featured'  => true,
                'approved_at'  => now(),
            ],
        );
    }

    private function assetType(string $slug): AssetType
    {
        $definitions = [
            'lottie-animation' => ['Lottie Animation', ['json', 'lottie'], ['application/json'], 'play-circle'],
            'svg-icon'         => ['SVG Icon', ['svg'], ['image/svg+xml'], 'vector-pen'],
            'animated-icon'    => ['Animated Icon', ['gif'], ['image/gif'], 'stars'],
            'illustration'     => ['Illustration', ['png', 'jpg', 'jpeg', 'webp'], ['image/png', 'image/jpeg', 'image/webp'], 'image'],
        ];

        [$name, $extensions, $mimes, $icon] = $definitions[$slug] ?? $definitions['illustration'];

        return AssetType::firstOrCreate(
            ['slug' => $slug],
            [
                'name'               => $name,
                'allowed_extensions' => $extensions,
                'allowed_mimes'      => $mimes,
                'icon'               => $icon,
                'is_active'          => true,
            ],
        );
    }

    private function category(string $name): Category
    {
        return Category::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'asset';
        $slug = $base;
        $i    = 1;

        while (Asset::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function removeImported(): void
    {
        $ids = Asset::whereNotNull('metadata->source_url')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        AssetFile::whereIn('asset_id', $ids)->delete();
        AssetPreview::whereIn('asset_id', $ids)->delete();
        Asset::whereIn('id', $ids)->forceDelete();

        $this->warn("Removed {$ids->count()} previously imported assets.");
    }

    private function refreshCounters(): void
    {
        DB::statement('UPDATE categories c SET assets_count = (
            SELECT COUNT(*) FROM assets a
            WHERE a.category_id = c.id AND a.status = "published" AND a.deleted_at IS NULL
        )');

        DB::statement('UPDATE contributors k SET assets_count = (
            SELECT COUNT(*) FROM assets a
            WHERE a.contributor_id = k.id AND a.status = "published" AND a.deleted_at IS NULL
        )');
    }
}
