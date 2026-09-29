<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Likes, collections, history, saved searches, reviews — brief §17, §10. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Enforces one-like-per-user at the DB, so a double-clicked
            // AJAX button cannot inflate the counter.
            $table->unique(['user_id', 'asset_id']);
            $table->index('asset_id');
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable();  // FK added in team migration

            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            $table->boolean('is_public')->default(false);
            $table->string('share_token', 64)->nullable()->unique();
            $table->unsignedBigInteger('assets_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'slug']);
            $table->index(['is_public', 'created_at']);
        });

        Schema::create('collection_asset', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->primary(['collection_id', 'asset_id']);
            $table->index('asset_id');
        });

        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'asset_id']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('query')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('notify_new_matches')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');   // 1–5, validated in FormRequest
            $table->text('comment')->nullable();

            // Only set when the reviewer actually owns/downloaded the asset.
            // Brief §29 forbids fabricated reviews; this is how "verified"
            // stays meaningful rather than decorative.
            $table->boolean('is_verified_purchase')->default(false);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['asset_id', 'user_id']);
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach ([
            'reviews', 'saved_searches', 'recently_viewed',
            'collection_asset', 'collections', 'likes',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
