<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CMS, SEO, email, analytics, audit — brief §25, §27, §28, §29, §33. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->longText('content')->nullable();
            $table->string('template', 80)->default('default');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('is_system')->default(false);  // terms, privacy — undeletable
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 240);
            $table->string('slug', 260)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();

            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('reading_minutes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('location', 80)->unique();   // header, footer_1, mobile
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('menu_items')->cascadeOnDelete();

            $table->string('label', 160);
            $table->string('url')->nullable();
            $table->string('route_name', 120)->nullable();
            $table->string('icon', 120)->nullable();
            $table->string('description', 240)->nullable();  // mega-menu blurb
            $table->boolean('is_mega')->default(false);
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('visible_to', 40)->default('all');  // all|guest|auth
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });

        // Polymorphic SEO for pages, posts, assets, packs, categories,
        // contributors — one table rather than six sets of columns.
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('seoable_type', 80);
            $table->unsignedBigInteger('seoable_id');

            $table->string('title', 240)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('focus_keywords')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->boolean('is_followable')->default(true);

            $table->string('og_title', 240)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card', 40)->nullable();
            $table->string('schema_type', 80)->nullable();
            $table->json('schema_overrides')->nullable();

            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 500);
            $table->string('target_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index('source_path');
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();       // welcome, payout_completed
            $table->string('name', 160);
            $table->string('subject', 240);
            $table->longText('body_html');
            $table->longText('body_text')->nullable();
            $table->json('available_variables')->nullable();
            $table->string('locale', 10)->default('en');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient');
            $table->string('subject', 300);
            $table->string('template_key', 120)->nullable();
            $table->string('provider', 60)->nullable();

            $table->enum('status', ['queued', 'sent', 'failed', 'bounced', 'complained'])
                ->default('queued');

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();

            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('recipient');
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 120)->nullable();

            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('url', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device', 40)->nullable();
            $table->string('browser', 60)->nullable();
            $table->json('utm')->nullable();
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('session_id');
        });

        // Search telemetry. Zero-result queries are the highest-signal
        // input to the catalogue roadmap, so they get their own flag.
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query', 300);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('results_count')->default(0);
            $table->boolean('is_zero_result')->default(false);
            $table->json('filters')->nullable();
            $table->foreignId('clicked_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('query');
            $table->index(['is_zero_result', 'created_at']);
        });

        /**
         * Append-only audit trail (brief §33). No updated_at, and the
         * model refuses update/delete. Normal administrators have no
         * route to edit these rows — that is the entire point.
         */
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label', 160)->nullable();  // survives user deletion

            $table->string('action', 120);
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });

        /**
         * Webhook idempotency. The unique constraint on
         * (provider, event_id) is what makes a replayed Stripe event a
         * no-op instead of a double payout.
         */
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 200);
            $table->string('event_type', 120);
            $table->json('payload')->nullable();
            $table->enum('status', ['received', 'processed', 'failed', 'ignored'])
                ->default('received');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);

            // Only the hash is stored. The plaintext key is shown once at
            // creation and is unrecoverable thereafter.
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 16);

            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('api_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 200);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_key_id', 'created_at']);
        });

        Schema::create('notifications_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('email_preferences')->nullable();
            $table->json('in_app_preferences')->nullable();
            $table->boolean('marketing_emails')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'notifications_preferences', 'api_usages', 'api_keys',
            'webhook_events', 'audit_logs', 'search_queries',
            'analytics_events', 'email_logs', 'email_templates',
            'redirects', 'seo_meta', 'menu_items', 'menus',
            'posts', 'blog_categories', 'pages',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
