<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contributor earnings, balances, payouts and the immutable ledger —
 * brief §23, §33.
 *
 * Design rule: `ledger_entries` is APPEND-ONLY. It has no updated_at,
 * the model blocks update/delete, and every balance is derived by
 * summing entries rather than mutating a stored running total. A
 * corrupted counter is recoverable; a mutated financial history is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('download_id')->nullable();  // FK added in ops migration

            $table->enum('source', [
                'individual_sale', 'pack_sale', 'subscription_download',
                'api_download', 'bonus', 'adjustment',
            ]);

            $table->decimal('gross_amount', 12, 4)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('net_amount', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            // pending → available after the holding period; a refund
            // moves it to reversed rather than deleting the row.
            $table->enum('status', ['pending', 'available', 'paid', 'reversed', 'on_hold'])
                ->default('pending');

            $table->timestamp('available_at')->nullable();
            $table->foreignId('payout_request_id')->nullable();  // FK added below
            $table->timestamps();

            $table->index(['contributor_id', 'status']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('contributor_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->unique()->constrained()->cascadeOnDelete();

            // A cache of the ledger, not the source of truth. Rebuildable
            // at any time by replaying ledger_entries.
            $table->decimal('pending_balance', 12, 4)->default(0);
            $table->decimal('available_balance', 12, 4)->default(0);
            $table->decimal('paid_balance', 12, 4)->default(0);
            $table->decimal('lifetime_earnings', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->boolean('is_on_hold')->default(false);
            $table->string('hold_reason')->nullable();
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payout_method_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 12, 4);
            $table->decimal('fee', 12, 4)->default(0);
            $table->decimal('tax_withheld', 12, 4)->default(0);
            $table->decimal('net_amount', 12, 4);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', [
                'requested', 'under_review', 'approved', 'processing',
                'completed', 'rejected', 'failed', 'cancelled',
            ])->default('requested');

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['contributor_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained()->cascadeOnDelete();

            $table->string('gateway', 40);
            $table->string('gateway_transaction_id')->nullable();
            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_transaction_id']);
        });

        /**
         * The immutable ledger. Note: no updated_at column — an entry that
         * can be updated is not a ledger. Corrections are new offsetting
         * entries, which is what `reversal_of_id` records.
         */
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();

            $table->enum('type', [
                'sale', 'subscription_share', 'refund_reversal',
                'payout', 'fee', 'tax', 'adjustment', 'bonus',
            ]);

            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('USD');

            // Polymorphic owner: a contributor, a team, or the platform.
            $table->string('owner_type', 60);
            $table->unsignedBigInteger('owner_id');

            // What caused this entry (order, refund, payout_request...).
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Running balance AFTER this entry, for audit reconstruction
            // without re-summing the whole table.
            $table->decimal('balance_after', 12, 4)->default(0);

            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();

            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // created_at only — deliberately no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_type', 'owner_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::table('contributor_earnings', function (Blueprint $table) {
            $table->foreign('payout_request_id')->references('id')
                ->on('payout_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contributor_earnings', fn (Blueprint $t) => $t->dropForeign(['payout_request_id']));

        foreach ([
            'ledger_entries', 'payout_transactions', 'payout_requests',
            'contributor_balances', 'contributor_earnings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
