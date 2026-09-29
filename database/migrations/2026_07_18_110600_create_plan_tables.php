<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans — brief §22. Nothing about pricing or entitlement
 * is hard-coded anywhere in the app; it all resolves from these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();

            $table->decimal('price_monthly', 12, 4)->default(0);
            $table->decimal('price_annual', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            // Gateway price identifiers. Nullable because a free or
            // manually-billed plan has no counterpart at the gateway.
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_annual_price_id')->nullable();

            $table->unsignedInteger('trial_days')->default(0);

            // NULL = unlimited. 0 = none. The distinction matters:
            // a nullable int expresses "no ceiling" without a sentinel.
            $table->unsignedInteger('download_limit_daily')->nullable();
            $table->unsignedInteger('download_limit_monthly')->nullable();
            $table->unsignedInteger('credits_monthly')->default(0);
            $table->unsignedInteger('team_seats')->default(1);

            $table->boolean('premium_access')->default(false);
            $table->boolean('source_file_access')->default(false);
            $table->boolean('editor_access')->default(false);
            $table->boolean('api_access')->default(false);
            $table->json('allowed_asset_types')->nullable();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_team_plan')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->json('available_countries')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_public', 'sort_order']);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('value', 120)->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_included')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
