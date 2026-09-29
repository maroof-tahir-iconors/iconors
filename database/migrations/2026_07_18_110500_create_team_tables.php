<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Team workspaces, seats and invitations — brief §18. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();

            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('logo')->nullable();
            $table->json('brand_colors')->nullable();
            $table->json('asset_preferences')->nullable();

            // seats_used is maintained transactionally alongside member
            // inserts; billing reads it, so it cannot drift silently.
            $table->unsignedInteger('seats_total')->default(1);
            $table->unsignedInteger('seats_used')->default(1);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('role', [
                'owner', 'administrator', 'billing_manager',
                'asset_manager', 'member', 'viewer',
            ])->default('member');

            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
            $table->index(['team_id', 'role']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();

            $table->string('email');
            $table->enum('role', [
                'administrator', 'billing_manager',
                'asset_manager', 'member', 'viewer',
            ])->default('member');

            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // One outstanding invite per email per team.
            $table->unique(['team_id', 'email']);
            $table->index('token');
        });

        // Deferred from the engagement migration: collections predate teams
        // in migration order, so the FK is attached once teams exists.
        Schema::table('collections', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        foreach (['team_invitations', 'team_members', 'teams'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
