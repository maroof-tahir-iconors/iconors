<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy: brief §25. Every one of these is admin-managed with
 * drag-and-drop ordering, hence sort_order on all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Asset types (SVG, 3D, Lottie, ...) carry their own validation
        // rules per brief §8 so adding a type is data, not a deploy.
        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('icon', 120)->nullable();
            $table->text('description')->nullable();

            $table->json('allowed_extensions');       // ["svg","png"]
            $table->json('allowed_mimes');            // ["image/svg+xml"]
            $table->unsignedInteger('max_file_size_kb')->default(102400);
            $table->json('required_source_formats')->nullable();
            $table->boolean('requires_preview')->default(true);
            $table->boolean('generates_preview')->default(true);
            $table->json('metadata_schema')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // Self-referencing to model category → subcategory without a
        // second table (brief lists both; one nested table is cleaner).
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 120)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('assets_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active', 'sort_order']);
            $table->index('is_featured');
        });

        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('preview_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamps();

            // Tag clouds and autocomplete both sort by this.
            $table->index('usage_count');
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('summary')->nullable();
            $table->longText('full_text')->nullable();

            $table->boolean('allows_commercial')->default(false);
            $table->boolean('allows_modification')->default(true);
            $table->boolean('allows_redistribution')->default(false);
            $table->boolean('requires_attribution')->default(true);
            $table->boolean('is_extended')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);            // SVG, PNG, GLB
            $table->string('slug', 50)->unique();
            $table->string('mime_type', 120)->nullable();
            $table->boolean('is_editable')->default(false);
            $table->boolean('is_vector')->default(false);
            $table->boolean('supports_transparency')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);           // Figma, Blender
            $table->string('slug', 120)->unique();
            $table->string('icon', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Colour filtering (brief §9). hex is indexed for exact match;
        // the h/s/l columns exist so "similar colour" is a range query
        // rather than a full-table scan converting hex on the fly.
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->string('hex', 7);
            $table->unsignedSmallInteger('hue')->default(0);
            $table->unsignedTinyInteger('saturation')->default(0);
            $table->unsignedTinyInteger('lightness')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('hex');
            $table->index('hue');
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('use_cases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'use_cases', 'industries', 'colors', 'software', 'formats',
            'licenses', 'tags', 'styles', 'categories', 'asset_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
