<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Downloads, licences, tickets, reports — brief §11, §25, §32. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contributor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            // How the user was entitled to this file. Recorded per download
            // so a later entitlement change cannot rewrite history — and so
            // subscription royalties can be attributed correctly.
            $table->enum('access_type', [
                'free', 'purchase', 'subscription', 'team', 'api', 'admin',
            ]);

            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('country', 2)->nullable();

            // Abuse signals (brief §32). Flagged rows feed the suspicious
            // download / account-sharing detector.
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['asset_id', 'created_at']);
            $table->index(['contributor_id', 'created_at']);
            $table->index(['access_type', 'created_at']);
            $table->index('is_flagged');
        });

        Schema::create('download_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 64)->unique();
            $table->foreignId('download_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('licensee_name', 200)->nullable();
            $table->json('terms_snapshot')->nullable();  // terms as they were at issue
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asset_id']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject', 240);
            $table->string('category', 80)->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', [
                'open', 'pending', 'awaiting_customer', 'resolved', 'closed',
            ])->default('open');

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body');

            // Staff-only note. Must never render in the customer view —
            // enforced in the policy, not just the Blade template.
            $table->boolean('is_internal_note')->default(false);

            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->cascadeOnDelete();

            $table->enum('type', ['copyright', 'abuse', 'spam', 'quality', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'under_review', 'actioned', 'dismissed'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('copyright_claims', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();

            $table->string('claimant_name', 200);
            $table->string('claimant_email');
            $table->string('claimant_organisation', 200)->nullable();
            $table->text('original_work_url')->nullable();
            $table->longText('statement');
            $table->boolean('sworn_declaration')->default(false);

            $table->enum('status', [
                'received', 'under_review', 'upheld', 'rejected', 'withdrawn',
            ])->default('received');

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('contributor_earnings', function (Blueprint $table) {
            $table->foreign('download_id')->references('id')->on('downloads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contributor_earnings', fn (Blueprint $t) => $t->dropForeign(['download_id']));

        foreach ([
            'copyright_claims', 'reports', 'ticket_messages', 'tickets',
            'download_licenses', 'downloads',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
