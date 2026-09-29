<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

/**
 * Asset type taxonomy — production safe.
 *
 * These rows previously existed only inside DemoCatalogueSeeder, which
 * also creates fake contributors and placeholder assets and therefore
 * must never run on a live site. The taxonomy itself is not demo data:
 * without it the contributor dashboard has no types to show and the
 * upload form has nothing to validate against. So it lives here, on its
 * own, and is keyed on slug so re-running it is harmless.
 *
 * `source_formats` drives the "File type required" block on the upload
 * screen. The three groups map to how a reviewer actually judges a
 * submission:
 *
 *   required  — every submission must include one of these
 *   any_one   — proof of authorship; any single one satisfies it
 *   optional  — accepted and useful, never blocking
 */
class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'SVG Icon',
                'slug' => 'svg-icon',
                'icon' => 'bounding-box',
                'description' => 'Single-file vector icons. Clean paths, no embedded rasters.',
                'allowed_extensions' => ['svg'],
                'allowed_mimes' => ['image/svg+xml'],
                'source_formats' => [
                    'required' => ['svg'],
                    'any_one' => ['ai', 'sketch', 'fig'],
                    'optional' => ['zip'],
                ],
                'max_file_size_kb' => 5120,
                'sort_order' => 1,
            ],
            [
                'name' => '3D Icon',
                'slug' => '3d-icon',
                'icon' => 'box',
                'description' => 'Rendered 3D objects with an accompanying source scene.',
                'allowed_extensions' => ['glb', 'gltf', 'obj', 'fbx'],
                'allowed_mimes' => ['model/gltf-binary', 'model/gltf+json', 'application/octet-stream'],
                'source_formats' => [
                    'required' => ['glb'],
                    'any_one' => ['blend', 'c4d', 'fbx'],
                    'optional' => ['png', 'zip'],
                ],
                'max_file_size_kb' => 51200,
                'sort_order' => 2,
            ],
            [
                'name' => 'Lottie Animation',
                'slug' => 'lottie-animation',
                'icon' => 'play-btn',
                'description' => 'Lightweight JSON animations exported for the Lottie player.',
                'allowed_extensions' => ['json'],
                'allowed_mimes' => ['application/json'],
                'source_formats' => [
                    'required' => ['json'],
                    'any_one' => ['aep', 'cv'],
                    'optional' => ['lottie', 'zip'],
                ],
                'max_file_size_kb' => 10240,
                'sort_order' => 3,
                'notes' => [
                    'Do not upload Lottie JSON which contains embedded images.',
                    'Make sure your Lottie JSON does not exceed 500 frames.',
                    'JSON exported directly from a Lottie editor is accepted on its own.',
                    'Name grouped files identically so they are matched during upload.',
                ],
            ],
            [
                'name' => 'Animated Icon',
                'slug' => 'animated-icon',
                'icon' => 'magic',
                'description' => 'Short looping icon animations.',
                'allowed_extensions' => ['gif', 'json', 'svg'],
                'allowed_mimes' => ['image/gif', 'application/json', 'image/svg+xml'],
                'source_formats' => [
                    'required' => ['json'],
                    'any_one' => ['aep', 'svg'],
                    'optional' => ['gif', 'zip'],
                ],
                'max_file_size_kb' => 10240,
                'sort_order' => 4,
            ],
            [
                'name' => 'Illustration',
                'slug' => 'illustration',
                'icon' => 'palette',
                'description' => 'Editorial and spot illustrations, vector or raster.',
                'allowed_extensions' => ['svg', 'png', 'jpg'],
                'allowed_mimes' => ['image/svg+xml', 'image/png', 'image/jpeg'],
                'source_formats' => [
                    'required' => ['svg'],
                    'any_one' => ['ai', 'psd', 'fig'],
                    'optional' => ['png', 'zip'],
                ],
                'max_file_size_kb' => 25600,
                'sort_order' => 5,
            ],
            [
                'name' => 'Sticker',
                'slug' => 'sticker',
                'icon' => 'sticky',
                'description' => 'Transparent-background stickers for chat and social.',
                'allowed_extensions' => ['png', 'webp'],
                'allowed_mimes' => ['image/png', 'image/webp'],
                'source_formats' => [
                    'required' => ['png'],
                    'any_one' => ['psd', 'ai'],
                    'optional' => ['webp', 'zip'],
                ],
                'max_file_size_kb' => 10240,
                'sort_order' => 6,
            ],
            [
                'name' => 'Emoji',
                'slug' => 'emoji',
                'icon' => 'emoji-smile',
                'description' => 'Emoji sets supplied as vector or transparent raster.',
                'allowed_extensions' => ['png', 'svg'],
                'allowed_mimes' => ['image/png', 'image/svg+xml'],
                'source_formats' => [
                    'required' => ['svg'],
                    'any_one' => ['ai', 'psd'],
                    'optional' => ['png'],
                ],
                'max_file_size_kb' => 5120,
                'sort_order' => 7,
            ],
            [
                'name' => 'Icon Pack',
                'slug' => 'icon-pack',
                'icon' => 'collection',
                'description' => 'A themed set uploaded together as one archive.',
                'allowed_extensions' => ['zip', 'svg'],
                'allowed_mimes' => ['application/zip', 'image/svg+xml'],
                'source_formats' => [
                    'required' => ['zip'],
                    'any_one' => ['ai', 'sketch', 'fig'],
                    'optional' => ['svg'],
                ],
                'max_file_size_kb' => 102400,
                'sort_order' => 8,
            ],
        ];

        foreach ($types as $data) {
            AssetType::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'description' => $data['description'],
                    'allowed_extensions' => $data['allowed_extensions'],
                    'allowed_mimes' => $data['allowed_mimes'],
                    'max_file_size_kb' => $data['max_file_size_kb'],
                    // Stored on the existing column; the upload screen reads
                    // the three groups straight off it.
                    'required_source_formats' => $data['source_formats'],
                    'metadata_schema' => isset($data['notes'])
                        ? ['upload_notes' => $data['notes']]
                        : null,
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }

        $this->command?->info('Seeded '.count($types).' asset types.');
    }
}
