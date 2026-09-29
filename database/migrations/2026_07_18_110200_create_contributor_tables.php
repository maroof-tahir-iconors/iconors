<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contributor onboarding and payout identity — brief §19, §23.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('display_name', 120);
            $table->string('slug', 140)->unique();
            $table->text('biography')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('website')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->json('social_links')->nullable();
            $table->json('specialties')->nullable();

            $table->string('avatar')->nullable();
            $table->string('cover_image')->nullable();

            $table->enum('status', [
                'pending', 'under_review', 'more_info_required',
                'approved', 'rejected', 'suspended',
            ])->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);

            // Per-contributor override. NULL = fall back to the global
            // rate in settings, so a platform-wide rate change doesn't
            // require touching every contributor row.
            $table->decimal('commission_rate', 5, 2)->nullable();

            // Denormalised counters. Maintained by events, not trusted
            // for financial decisions — money always reads the ledger.
            $table->unsignedBigInteger('assets_count')->default(0);
            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedBigInteger('followers_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index('is_verified');
        });

        Schema::create('contributor_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('full_name', 160);
            $table->string('display_name', 120);
            $table->string('country', 2);
            $table->text('biography');
            $table->string('portfolio_url');
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();
            $table->json('specialties');
            $table->json('sample_files')->nullable();

            $table->string('payout_preference', 40)->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->timestamp('terms_accepted_at')->nullable();

            $table->enum('status', [
                'pending', 'under_review', 'more_info_required',
                'approved', 'rejected',
            ])->default('pending');

            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['paypal', 'bank_transfer', 'stripe_connect']);

            // Encrypted at the model layer — account numbers and IBANs
            // must never sit readable in a DB dump.
            $table->text('details');

            $table->string('display_hint', 60)->nullable();  // "•••• 4821"
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contributor_id', 'is_default']);
        });

        Schema::create('contributor_tax_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->unique()
                ->constrained()->cascadeOnDelete();

            $table->string('legal_name', 200)->nullable();
            $table->enum('entity_type', ['individual', 'business'])->default('individual');
            $table->string('country', 2)->nullable();
            $table->text('tax_id')->nullable();          // encrypted at model layer
            $table->text('address')->nullable();         // encrypted at model layer
            $table->string('tax_form_type', 40)->nullable();
            $table->decimal('withholding_rate', 5, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contributor_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contributor_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'contributor_follows', 'contributor_tax_info', 'payout_methods',
            'contributor_applications', 'contributors',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
