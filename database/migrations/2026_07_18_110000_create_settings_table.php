<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central key/value store for every admin-controllable setting:
 * branding, general, SEO, mail, payment credentials, integrations.
 *
 * Credentials (SMTP passwords, gateway secrets) set is_encrypted so
 * SettingsService transparently casts them through the app encrypter —
 * they are never readable from a DB dump. Brief §21, §27, §32.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // e.g. branding, general, seo, mail, payment, analytics, marketplace
            $table->string('group', 64)->index();
            $table->string('key', 128)->unique();

            // Nullable: an unset credential is meaningfully different from ''
            $table->text('value')->nullable();

            // Drives casting + which admin form control renders
            $table->enum('type', [
                'string', 'text', 'boolean', 'integer', 'float',
                'json', 'color', 'file', 'select',
            ])->default('string');

            // Encrypt at rest. Never true for anything is_public.
            $table->boolean('is_encrypted')->default(false);

            // Safe to expose to the frontend / unauthenticated views?
            // Guards against a colour setting and an API secret being
            // shipped to the browser by the same careless helper.
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->index(['group', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
