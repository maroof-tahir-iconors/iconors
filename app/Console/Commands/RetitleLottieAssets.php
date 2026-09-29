<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Improves the titles of imported Lottie assets.
 *
 * The import names them from their category ("Animation 138") because the
 * source filenames are bare numbers. But a Bodymovin export carries the
 * original composition name in its `nm` field — "Heart Icon", "Thumb
 * Animation", "Hand Slide" — which is far better copy and costs nothing
 * to read: the files are already on disk, so this needs no network at all.
 *
 * Source names are not unique (63 files are all called "Heart Icon"), so
 * duplicates get a numeric suffix rather than being left ambiguous.
 *
 *   php artisan iconors:retitle --dry-run
 *   php artisan iconors:retitle
 */
class RetitleLottieAssets extends Command
{
    protected $signature = 'iconors:retitle
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Retitle imported Lottie assets using the name embedded in each file';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $assets = Asset::query()
            ->whereNotNull('metadata->source_url')
            ->whereHas('assetType', fn ($q) => $q->where('slug', 'lottie-animation'))
            ->with('files')
            ->get();

        if ($assets->isEmpty()) {
            $this->warn('No imported Lottie assets found. Run iconors:import first.');

            return self::SUCCESS;
        }

        $this->info($assets->count().' imported Lottie assets to inspect.');

        $counters = [];
        $changed  = 0;
        $skipped  = 0;

        foreach ($assets as $asset) {
            $name = $this->embeddedName($asset);

            // Numeric or empty composition names are no better than what the
            // import already produced, so those keep their existing title.
            if ($name === null) {
                $skipped++;

                continue;
            }

            $counters[$name] ??= 0;
            $counters[$name]++;

            // Only suffix when the name genuinely repeats. A one-off keeps
            // its clean name rather than becoming "Tilt Screen 01".
            $title = $counters[$name] > 1
                ? $name.' '.str_pad((string) $counters[$name], 2, '0', STR_PAD_LEFT)
                : $name;

            if ($asset->title === $title) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('  %-28s -> %s', Str::limit($asset->title, 26), $title));
            } else {
                $asset->update(['title' => $title, 'slug' => $this->uniqueSlug($title, $asset->id)]);
            }

            $changed++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("{$changed} titles would change, {$skipped} left as they are.");
            $this->comment('Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $this->info("Retitled {$changed} assets. {$skipped} left unchanged.");

        return self::SUCCESS;
    }

    /** Reads the Bodymovin composition name from the stored file. */
    private function embeddedName(Asset $asset): ?string
    {
        $file = $asset->files->firstWhere('is_source', true) ?? $asset->files->first();

        if (! $file || ! Storage::disk($file->disk)->exists($file->path)) {
            return null;
        }

        try {
            $data = json_decode(Storage::disk($file->disk)->get($file->path), true);
        } catch (\Throwable) {
            return null;
        }

        $name = trim((string) ($data['nm'] ?? ''));

        // "12", "3-1" and the like carry no meaning — reject them.
        if ($name === '' || preg_match('/^[\d\s\-_.]+$/', $name)) {
            return null;
        }

        return Str::limit(Str::title($name), 80, '');
    }

    private function uniqueSlug(string $title, int $ignoreId): string
    {
        $base = Str::slug($title) ?: 'animation';
        $slug = $base;
        $i    = 1;

        while (Asset::withTrashed()->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
