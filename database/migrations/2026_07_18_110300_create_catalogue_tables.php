<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assets, their files, previews, versions and packs — brief §8, §11, §20.
 *
 * The security-critical split lives here: asset_previews are public and
 * CDN-served; asset_files are private-disk paths that must NEVER be
 * rendered into a URL. Two tables, not one with a boolean, because a
 * boolean gets forgotten in a query and leaks the entire inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            $table->boolean('is_premium')->default(false);
            $table->decimal('price', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'changes_requested',
                'approved', 'rejected', 'scheduled', 'published',
                'suspended', 'archived',
            ])->default('draft');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->boolean('is_featured')->default(false);

            $table->unsignedBigInteger('assets_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['contributor_id', 'status']);
            $table->index('is_featured');
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('style_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();

            // Commercial terms. is_premium and price are separate because a
            // premium asset can be price 0 when it is subscription-only.
            $table->boolean('is_premium')->default(false);
            $table->decimal('price', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('subscription_only')->default(false);

            $table->boolean('is_animated')->default(false);
            $table->boolean('is_3d')->default(false);
            $table->boolean('has_transparency')->default(false);
            $table->string('orientation', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->json('dominant_colors')->nullable();

            $table->enum('status', [
                'draft', 'uploading', 'processing', 'validation_failed',
                'submitted', 'under_review', 'changes_requested',
                'approved', 'rejected', 'scheduled', 'published',
                'suspended', 'copyright_review', 'archived',
            ])->default('draft');

            $table->text('status_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();

            $table->boolean('is_featured')->default(false);

            // Denormalised engagement counters — display only.
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedBigInteger('rating_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Composite index driving the main marketplace listing:
            // published assets, newest first, filtered by type.
            $table->index(['status', 'published_at']);
            $table->index(['asset_type_id', 'status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['contributor_id', 'status']);
            $table->index(['is_premium', 'status']);
            $table->index(['is_featured', 'status']);
            $table->index('downloads_count');
        });

        /**
         * PRIVATE. `disk` + `path` point at a non-public filesystem.
         * There is deliberately no `url` column — the only access route
         * is a short-TTL signed URL minted by SignedDownloadService.
         */
        Schema::create('asset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->nullable()->constrained()->nullOnDelete();

            $table->string('disk', 40)->default('private');
            $table->string('path');
            $table->string('original_name');
            $table->string('extension', 20);
            $table->string('mime_type', 140);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();   // sha256, dedupe + integrity

            $table->boolean('is_source')->default(true);
            $table->boolean('requires_purchase')->default(true);

            // Malware / SVG-sanitisation gate (brief §32). Files are not
            // downloadable until this reads 'clean'.
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'failed'])
                ->default('pending');
            $table->timestamp('scanned_at')->nullable();

            $table->timestamps();

            $table->index(['asset_id', 'is_source']);
            $table->index('checksum');
            $table->index('scan_status');
        });

        /** PUBLIC. Safe to CDN-serve; watermarked where settings require. */
        Schema::create('asset_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();

            $table->enum('type', [
                'thumbnail', 'small', 'medium', 'large',
                'lottie', 'video', 'model', 'sprite',
            ])->default('medium');

            $table->string('disk', 40)->default('public');
            $table->string('path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_watermarked')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['asset_id', 'type']);
            $table->index(['asset_id', 'is_primary']);
        });

        Schema::create('asset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('changelog')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'version']);
        });

        // --- Pivots ---------------------------------------------------
        Schema::create('asset_tag', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('asset_format', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'format_id']);
            $table->index('format_id');
        });

        Schema::create('asset_software', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('software_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'software_id']);
            $table->index('software_id');
        });

        Schema::create('asset_industry', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'industry_id']);
        });

        Schema::create('asset_use_case', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('use_case_id')->constrained()->cascadeOnDelete();
            $table->primary(['asset_id', 'use_case_id']);
        });

        Schema::create('asset_color', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('coverage_percent')->default(0);
            $table->primary(['asset_id', 'color_id']);
            $table->index('color_id');
        });

        Schema::create('pack_asset', function (Blueprint $table) {
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['pack_id', 'asset_id']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        foreach ([
            'pack_asset', 'asset_color', 'asset_use_case', 'asset_industry',
            'asset_software', 'asset_format', 'asset_tag',
            'asset_versions', 'asset_previews', 'asset_files',
            'assets', 'packs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
