<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-asset-type upload permission.
 *
 * Approval was previously all-or-nothing: an approved contributor could
 * upload anything. That is too coarse for a marketplace where the review
 * bar differs sharply by medium — someone vetted on flat SVG icons has
 * not been vetted on 3D scenes or Lottie rigging.
 *
 * So access is granted per type. A contributor asks for a type, supplies
 * a portfolio URL as evidence, and a reviewer decides. No row means no
 * access, which makes the default deny rather than allow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_asset_type_access', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'requested', 'under_review', 'approved', 'rejected', 'revoked',
            ])->default('requested');

            // Evidence supplied with the request. Required at the form
            // layer; nullable here because a staff-granted row has no
            // request behind it.
            $table->string('portfolio_url')->nullable();
            $table->text('note')->nullable();

            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // One row per contributor per type: re-requesting updates the
            // existing row rather than stacking duplicates in the queue.
            $table->unique(['contributor_id', 'asset_type_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_asset_type_access');
    }
};
