<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Flags which assets are actually COLOURFUL, by reading their files.
 *
 * A large share of the catalogue is monochrome line art drawn in black or
 * near-black. That is perfectly good artwork, but a shop window full of it
 * looks empty and lifeless, so the homepage needs to be able to select for
 * colour. There is no way to know from the metadata alone — it has to be
 * measured from the file.
 *
 * Writes `metadata->is_colourful` and populates `dominant_colors`.
 *
 *   php artisan iconors:analyse-colours
 *   php artisan iconors:analyse-colours --fresh
 */
class AnalyseAssetColours extends Command
{
    protected $signature = 'iconors:analyse-colours
        {--fresh : Re-analyse assets that already carry a verdict}';

    protected $description = 'Detect which assets are colourful vs monochrome by inspecting their files';

    /** Saturation floor for a single colour to count as vivid. */
    private const MIN_SATURATION = 0.25;

    /** Proportion of an asset's colours that must be vivid to qualify. */
    private const MIN_VIVID_SHARE = 0.25;

    public function handle(): int
    {
        $query = Asset::with('files')->whereHas('files');

        if (! $this->option('fresh')) {
            $query->where(function ($q) {
                $q->whereNull('metadata->is_colourful');
            });
        }

        $assets = $query->get();

        if ($assets->isEmpty()) {
            $this->info('Nothing to analyse. Use --fresh to re-check everything.');

            return self::SUCCESS;
        }

        $this->info("Analysing {$assets->count()} assets…");
        $bar = $this->output->createProgressBar($assets->count());

        $colourful = 0;
        $mono      = 0;
        $skipped   = 0;

        foreach ($assets as $asset) {
            $bar->advance();

            $file = $asset->files->firstWhere('is_source', true) ?? $asset->files->first();

            if (! $file || ! Storage::disk($file->disk)->exists($file->path)) {
                $skipped++;

                continue;
            }

            try {
                $contents = Storage::disk($file->disk)->get($file->path);
            } catch (\Throwable) {
                $skipped++;

                continue;
            }

            $colours = match (strtolower($file->extension)) {
                'svg'   => $this->coloursFromSvg($contents),
                'json'  => $this->coloursFromLottie($contents),
                // Raster formats are assumed colourful: they are photos and
                // illustrations, not line art.
                default => [['h' => 0, 's' => 1, 'l' => 0.5, 'hex' => null]],
            };

            $vivid = array_values(array_filter(
                $colours,
                fn ($c) => $c['s'] >= self::MIN_SATURATION && $c['l'] > 0.08 && $c['l'] < 0.95,
            ));

            /*
             * "Has one coloured pixel" is not the same as "is colourful".
             * A black line icon with a single red dot passed the first
             * version of this test and still rendered as a black glyph.
             *
             * So colour has to be a meaningful SHARE of the artwork, not
             * merely present: at least a quarter of the declared colours,
             * or two distinct vivid hues.
             */
            $total       = max(1, count($colours));
            $vividShare  = count($vivid) / $total;
            $distinctHues = count(array_unique(array_map(
                fn ($c) => (int) round($c['h'] / 30),   // 30° buckets
                $vivid,
            )));

            $isColourful = $vividShare >= self::MIN_VIVID_SHARE || $distinctHues >= 2;
            $isColourful ? $colourful++ : $mono++;

            $metadata = $asset->metadata ?? [];
            $metadata['is_colourful'] = $isColourful;

            DB::table('assets')->where('id', $asset->id)->update([
                'metadata'        => json_encode($metadata),
                'dominant_colors' => json_encode(array_values(array_unique(array_filter(
                    array_column(array_slice($vivid, 0, 5), 'hex')
                )))),
            ]);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Colourful: {$colourful}");
        $this->line("Monochrome: {$mono}");

        if ($skipped > 0) {
            $this->warn("Skipped (file missing or unreadable): {$skipped}");
        }

        return self::SUCCESS;
    }

    /** @return list<array{h: float, s: float, l: float, hex: ?string}> */
    private function coloursFromSvg(string $svg): array
    {
        $colours = [];

        // Hex literals anywhere in fills, strokes, stop-colors or style.
        preg_match_all('/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $svg, $hexes);

        foreach ($hexes[1] as $hex) {
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            $colours[] = $this->hslFrom(
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
                '#'.strtoupper($hex),
            );
        }

        preg_match_all('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $svg, $rgbs, PREG_SET_ORDER);

        foreach ($rgbs as $rgb) {
            $colours[] = $this->hslFrom((int) $rgb[1], (int) $rgb[2], (int) $rgb[3], null);
        }

        // Named colours that are unambiguously not greyscale. Deliberately
        // a short list — the goal is detecting colour, not exhaustiveness.
        foreach (['red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'cyan', 'magenta', 'teal', 'gold'] as $name) {
            if (preg_match('/(?:fill|stroke|stop-color)\s*[:=]\s*["\']?'.$name.'\b/i', $svg)) {
                $colours[] = ['h' => 0, 's' => 1, 'l' => 0.5, 'hex' => null];
            }
        }

        return $colours;
    }

    /**
     * Bodymovin stores colours as normalised RGBA arrays under `c.k`.
     * Walking the whole tree is simpler and more reliable than trying to
     * model the schema.
     *
     * @return list<array{h: float, s: float, l: float, hex: ?string}>
     */
    private function coloursFromLottie(string $json): array
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        $colours = [];

        $walk = function ($node) use (&$walk, &$colours) {
            if (! is_array($node)) {
                return;
            }

            // A colour property looks like {"c": {"k": [r, g, b, a]}} with
            // each channel in 0..1.
            if (isset($node['c']['k']) && is_array($node['c']['k'])) {
                $k = $node['c']['k'];

                if (count($k) >= 3 && is_numeric($k[0]) && is_numeric($k[1]) && is_numeric($k[2])) {
                    $r = (int) round($k[0] * 255);
                    $g = (int) round($k[1] * 255);
                    $b = (int) round($k[2] * 255);

                    $colours[] = $this->hslFrom($r, $g, $b, sprintf('#%02X%02X%02X', $r, $g, $b));
                }
            }

            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        $walk($data);

        return $colours;
    }

    /** @return array{h: float, s: float, l: float, hex: ?string} */
    private function hslFrom(int $r, int $g, int $b, ?string $hex): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;
        $d   = $max - $min;

        // Saturation is what separates colour from greyscale: a black,
        // white or grey glyph has effectively none regardless of lightness.
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));

        $h = 0;

        if ($d != 0) {
            $h = match ($max) {
                $r      => 60 * fmod((($g - $b) / $d), 6),
                $g      => 60 * ((($b - $r) / $d) + 2),
                default => 60 * ((($r - $g) / $d) + 4),
            };
        }

        return ['h' => $h < 0 ? $h + 360 : $h, 's' => $s, 'l' => $l, 'hex' => $hex];
    }
}
