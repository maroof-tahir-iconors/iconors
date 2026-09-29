<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cart → order → payment → invoice — brief §21.
 *
 * Every monetary column is DECIMAL(12,4). Floats are never used for
 * money: 0.1 + 0.2 !== 0.3 in binary floating point, and that error
 * compounds across royalty splits until the ledger stops balancing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            // Nullable: guests get a session-keyed cart, merged on login.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 120)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();

            // Price is captured at add-to-cart time. If a contributor
            // repriced an asset mid-session, the customer pays what they
            // were shown — and the difference is visible, not silent.
            $table->decimal('unit_price', 12, 4);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['cart_id', 'asset_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('discount_total', 12, 4)->default(0);
            $table->decimal('tax_total', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->decimal('refunded_total', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->foreignId('coupon_id')->nullable();  // FK added below
            $table->string('coupon_code', 60)->nullable();

            $table->enum('status', [
                'pending', 'processing', 'paid', 'failed',
                'cancelled', 'refunded', 'partially_refunded',
            ])->default('pending');

            $table->string('gateway', 40)->nullable();
            $table->json('billing_details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('paid_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contributor_id')->nullable()->constrained()->nullOnDelete();

            // Denormalised so a historical invoice still renders correctly
            // after the asset is renamed, repriced, or deleted entirely.
            $table->string('title', 220);
            $table->decimal('unit_price', 12, 4);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 12, 4);

            // Royalty split frozen at purchase time. Changing the global
            // commission rate later must never retroactively alter what a
            // contributor already earned.
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('contributor_earning', 12, 4)->default(0);
            $table->decimal('platform_earning', 12, 4)->default(0);

            $table->timestamps();

            $table->index('order_id');
            $table->index('contributor_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('gateway', 40);
            $table->string('gateway_payment_id')->nullable();
            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('USD');

            $table->enum('status', [
                'pending', 'processing', 'succeeded',
                'failed', 'cancelled', 'refunded',
            ])->default('pending');

            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();

            // A gateway payment id must map to exactly one payment row.
            // This is the last line of defence against a replayed webhook
            // creating a duplicate charge record.
            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('gateway_refund_id')->nullable();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->boolean('deduct_from_contributor')->default(true);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('tax_total', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->json('billing_details')->nullable();
            $table->enum('status', ['draft', 'issued', 'paid', 'void'])->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();

            $table->enum('type', ['percentage', 'fixed', 'trial_extension']);
            $table->decimal('value', 12, 4);
            $table->string('currency', 3)->nullable();
            $table->decimal('minimum_order', 12, 4)->nullable();

            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);

            $table->json('applicable_plan_ids')->nullable();
            $table->json('applicable_asset_ids')->nullable();
            $table->json('allowed_countries')->nullable();
            $table->json('allowed_user_ids')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 4);
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropForeign(['coupon_id']));

        foreach ([
            'coupon_usages', 'coupons', 'invoices', 'refunds',
            'payments', 'order_items', 'orders', 'cart_items', 'carts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
