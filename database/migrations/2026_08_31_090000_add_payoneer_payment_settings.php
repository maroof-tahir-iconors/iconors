<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Payoneer gateway settings rows.
 *
 * A migration rather than "re-run SettingsSeeder": that seeder writes `value`
 * on every row it owns, so running it again to pick up new keys would reset
 * every already-saved Stripe and PayPal credential back to null. This only
 * ever INSERTS rows that are missing, and never touches one that exists — so
 * it is safe on an install where credentials are already configured.
 */
return new class extends Migration
{
    /**
     * Mirrors the shape of the existing gateways: an enable toggle, a
     * sandbox/live mode, and encrypted credentials seeded empty. Secrets
     * belong in the admin UI or .env, never in a migration in git.
     */
    private const ROWS = [
        ['key' => 'payoneer_enabled',        'type' => 'boolean', 'is_encrypted' => false, 'value' => '0'],
        ['key' => 'payoneer_mode',           'type' => 'select',  'is_encrypted' => false, 'value' => 'sandbox'],
        ['key' => 'payoneer_program_id',     'type' => 'string',  'is_encrypted' => true,  'value' => null],
        ['key' => 'payoneer_api_username',   'type' => 'string',  'is_encrypted' => true,  'value' => null],
        ['key' => 'payoneer_api_password',   'type' => 'string',  'is_encrypted' => true,  'value' => null],
        ['key' => 'payoneer_webhook_secret', 'type' => 'string',  'is_encrypted' => true,  'value' => null],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::ROWS as $row) {
            // insertOrIgnore relies on the unique index on `settings.key`, so a
            // re-run is a no-op rather than a duplicate-key failure.
            DB::table('settings')->insertOrIgnore([
                'group' => 'payment',
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => $row['type'],
                'is_encrypted' => $row['is_encrypted'],
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', array_column(self::ROWS, 'key'))
            ->delete();
    }
};
