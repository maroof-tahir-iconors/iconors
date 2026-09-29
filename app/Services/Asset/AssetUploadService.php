<?php

namespace App\Services\Asset;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Handles a contributor's asset upload end to end — brief §20.
 *
 * Source files land on the PRIVATE disk; only generated previews go
 * public. Nothing a contributor uploads is ever served from the public
 * disk unmodified.
 */
class AssetUploadService
{
    public function __construct(
        private readonly SvgSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Contributor $contributor, UploadedFile $file, array $data): Asset
    {
        $type = AssetType::findOrFail($data['asset_type_id']);

        $this->validateAgainstType($file, $type);

        $extension = strtolower($file->getClientOriginalExtension());
        $contents  = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Uploaded file could not be read.');
        }

        $sanitisationNotes = [];

        // SVG is sanitised BEFORE storage, not before rendering. Storing the
        // raw file and cleaning on output means every future code path that
        // reads it has to remember to sanitise — one that forgets is an XSS.
        if ($extension === 'svg') {
            $result = $this->sanitizer->sanitize($contents);

            if ($result['svg'] === '') {
                throw new RuntimeException(
                    'This SVG could not be processed: '.implode(', ', $result['removals'])
                );
            }

            $contents = $result['svg'];
            $sanitisationNotes = $result['removals'];
        }

        return DB::transaction(function () use ($contributor, $data, $type, $file, $extension, $contents, $sanitisationNotes) {
            $slug = $this->uniqueSlug($data['title']);

            $asset = Asset::create([
                'contributor_id'    => $contributor->id,
                'asset_type_id'     => $type->id,
                'category_id'       => $data['category_id'] ?? null,
                'style_id'          => $data['style_id'] ?? null,
                'license_id'        => $data['license_id'] ?? null,
                'title'             => $data['title'],
                'slug'              => $slug,
                'description'       => $data['description'] ?? null,
                'is_premium'        => (bool) ($data['is_premium'] ?? false),
                'price'             => (bool) ($data['is_premium'] ?? false) ? ($data['price'] ?? 0) : 0,
                'is_animated'       => (bool) ($data['is_animated'] ?? false),
                'is_3d'             => (bool) ($data['is_3d'] ?? false),
                // Goes straight to review. A contributor cannot self-publish
                // — brief §20 requires an approval step.
                'status'            => 'submitted',
                'metadata'          => $sanitisationNotes ? ['sanitisation' => $sanitisationNotes] : null,
            ]);

            $path = "assets/{$asset->id}/source.{$extension}";
            Storage::disk('private')->put($path, $contents);

            AssetFile::create([
                'asset_id'      => $asset->id,
                'disk'          => 'private',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'extension'     => $extension,
                'mime_type'     => $file->getClientMimeType(),
                'size_bytes'    => strlen($contents),
                'checksum'      => hash('sha256', $contents),
                'is_source'     => true,
                // SVG is cleared by sanitisation. Binary formats stay
                // 'pending' until a real scanner clears them, and the
                // download service refuses anything not 'clean'.
                'scan_status'   => $extension === 'svg' ? 'clean' : 'pending',
                'scanned_at'    => $extension === 'svg' ? now() : null,
            ]);

            $this->generatePreview($asset, $extension, $contents);

            if (! empty($data['tags'])) {
                $this->syncTags($asset, $data['tags']);
            }

            return $asset;
        });
    }

    /**
     * Validates the upload against the asset type's own configured rules,
     * which are database rows — so adding a format is data, not a deploy.
     */
    private function validateAgainstType(UploadedFile $file, AssetType $type): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed   = array_map('strtolower', (array) $type->allowed_extensions);

        if ($allowed && ! in_array($extension, $allowed, true)) {
            throw new RuntimeException(
                "{$type->name} accepts only: ".implode(', ', $allowed).'.'
            );
        }

        // Checked against the real sniffed MIME, not the client-supplied
        // header — a browser will happily send whatever it is told.
        $allowedMimes = array_map('strtolower', (array) $type->allowed_mimes);
        $actualMime   = strtolower((string) $file->getMimeType());

        if ($allowedMimes && ! in_array($actualMime, $allowedMimes, true)) {
            throw new RuntimeException(
                "File content does not match {$type->name}. Detected: {$actualMime}."
            );
        }

        $maxKb = (int) $type->max_file_size_kb;

        if ($maxKb > 0 && $file->getSize() > $maxKb * 1024) {
            throw new RuntimeException(
                'File exceeds the '.number_format($maxKb / 1024, 1).' MB limit for '.$type->name.'.'
            );
        }
    }

    /**
     * For SVG the sanitised markup doubles as its own preview — it is
     * already safe to serve. Other formats get a placeholder until the
     * queued preview generator (Intervention/ffmpeg) processes them.
     */
    private function generatePreview(Asset $asset, string $extension, string $contents): void
    {
        if ($extension !== 'svg') {
            return;
        }

        $path = "previews/{$asset->id}/preview.svg";
        Storage::disk('public')->put($path, $contents);

        AssetPreview::create([
            'asset_id'   => $asset->id,
            'type'       => 'medium',
            'disk'       => 'public',
            'path'       => $path,
            'width'      => 240,
            'height'     => 240,
            'size_bytes' => strlen($contents),
            'is_primary' => true,
        ]);
    }

    /** @param list<string>|string $tags */
    private function syncTags(Asset $asset, array|string $tags): void
    {
        $names = is_string($tags)
            ? array_filter(array_map('trim', explode(',', $tags)))
            : $tags;

        $ids = [];

        foreach (array_slice($names, 0, 20) as $name) {
            $name = Str::lower(trim($name));

            if ($name === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );

            $tag->increment('usage_count');
            $ids[] = $tag->id;
        }

        $asset->tags()->sync($ids);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'asset';
        $slug = $base;
        $i    = 1;

        // withTrashed: a soft-deleted asset still occupies the unique index,
        // so ignoring it would produce an integrity violation on insert.
        while (Asset::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
