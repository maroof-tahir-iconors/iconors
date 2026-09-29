<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gateway and SMTP credential handling — brief §21, §27, §32.
 *
 * These tests are about one thing: a secret entered here must not be
 * readable anywhere afterwards except through the decrypting service.
 * Not in the database, not in an audit log, not in a rendered form.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_a_customer_cannot_reach_payment_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Free Customer');

        $this->actingAs($user)->get(route('admin.settings.payments'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.settings.payments'), [])->assertForbidden();
    }

    /**
     * Separation of duty: a content editor administers content, not money.
     */
    public function test_a_content_editor_cannot_reach_payment_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Content Editor');

        $this->actingAs($user)->get(route('admin.settings.payments'))->assertForbidden();
    }

    public function test_a_finance_manager_can_save_gateway_credentials(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'stripe_secret' => 'sk_test_SUPERSECRETVALUE',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('sk_test_SUPERSECRETVALUE', app(SettingsService::class)->get('stripe_secret'));
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'stripe_secret' => 'sk_test_PLAINTEXTCHECK',
            ]));

        $raw = DB::table('settings')->where('key', 'stripe_secret')->value('value');

        $this->assertStringNotContainsString('PLAINTEXTCHECK', $raw);
        $this->assertStringStartsWith('eyJpdiI6', $raw, 'Value should be a Laravel encrypted payload.');
    }

    /**
     * The form shows a masked hint, not the real value. Submitting it
     * unchanged sends a blank field — which must be treated as "keep",
     * not "clear". Getting this wrong silently wipes a live gateway
     * credential the first time someone edits an unrelated setting.
     */
    public function test_submitting_a_blank_secret_keeps_the_stored_value(): void
    {
        $admin = $this->financeManager();

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'stripe_secret' => 'sk_test_ORIGINAL',
        ]));

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'stripe_secret' => '',
            'stripe_mode'   => 'live',
        ]));

        $service = app(SettingsService::class);

        $this->assertSame('sk_test_ORIGINAL', $service->get('stripe_secret'));
        $this->assertSame('live', $service->get('stripe_mode'));
    }

    public function test_a_gateway_cannot_be_enabled_without_credentials(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'stripe_enabled' => '1',
                'stripe_secret'  => '',
            ]))
            ->assertSessionHasErrors('stripe_enabled');

        $this->assertFalse((bool) app(SettingsService::class)->get('stripe_enabled'));
    }

    public function test_a_gateway_can_be_enabled_once_credentials_exist(): void
    {
        $admin = $this->financeManager();

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'stripe_secret' => 'sk_test_READY',
        ]));

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'stripe_enabled' => '1',
            'stripe_secret'  => '',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue((bool) app(SettingsService::class)->get('stripe_enabled'));
    }

    public function test_payoneer_cannot_be_enabled_without_credentials(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'payoneer_enabled'     => '1',
                'payoneer_api_password' => '',
            ]))
            ->assertSessionHasErrors('payoneer_enabled');

        $this->assertFalse((bool) app(SettingsService::class)->get('payoneer_enabled'));
    }

    public function test_payoneer_can_be_enabled_once_credentials_exist(): void
    {
        $admin = $this->financeManager();

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'payoneer_program_id'   => 'PROG-READY',
            'payoneer_api_username' => 'api_user',
            'payoneer_api_password' => 'pw_READY',
        ]));

        $this->actingAs($admin)->post(route('admin.settings.payments'), $this->paymentPayload([
            'payoneer_enabled'      => '1',
            'payoneer_api_password' => '',
        ]))->assertSessionHasNoErrors();

        $service = app(SettingsService::class);

        $this->assertTrue((bool) $service->get('payoneer_enabled'));
        // The blank resubmit above must not have wiped the stored password.
        $this->assertSame('pw_READY', $service->get('payoneer_api_password'));
    }

    public function test_payoneer_credentials_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'payoneer_api_password' => 'NEVERPLAINTEXT',
            ]));

        $raw = DB::table('settings')->where('key', 'payoneer_api_password')->value('value');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('NEVERPLAINTEXT', (string) $raw);
        $this->assertSame('NEVERPLAINTEXT', app(SettingsService::class)->get('payoneer_api_password'));
    }

    public function test_payoneer_mode_rejects_an_unknown_value(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'payoneer_mode' => 'production',
            ]))
            ->assertSessionHasErrors('payoneer_mode');
    }

    /**
     * Audit logs are widely readable by staff. Recording WHICH setting
     * changed is the point; recording its value would turn the audit
     * trail into a plaintext credential store.
     */
    public function test_the_audit_log_records_the_change_but_never_the_secret(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'stripe_secret' => 'sk_test_NEVERLOGGED',
            ]));

        $log = DB::table('audit_logs')->where('action', 'settings.updated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('NEVERLOGGED', (string) $log->new_values);
        $this->assertStringNotContainsString('stripe_secret', (string) $log->new_values);
        $this->assertStringContainsString('stripe_mode', (string) $log->new_values);
    }

    public function test_secrets_are_excluded_from_the_public_settings_payload(): void
    {
        $this->actingAs($this->financeManager())
            ->post(route('admin.settings.payments'), $this->paymentPayload([
                'stripe_secret' => 'sk_test_NOTPUBLIC',
            ]));

        $public = app(SettingsService::class)->public();

        $this->assertArrayNotHasKey('stripe_secret', $public);
        $this->assertArrayNotHasKey('paypal_secret', $public);
        // Branding, by contrast, is meant to be public.
        $this->assertArrayHasKey('brand_primary', $public);
    }

    public function test_mail_settings_require_the_smtp_permission(): void
    {
        $finance = $this->financeManager();

        // Finance manages money, not mail servers.
        $this->actingAs($finance)->get(route('admin.settings.mail'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('Super Administrator');
        $this->actingAs($admin)->post(route('admin.settings.mail'), [
            'mail_driver'     => 'log',
            'mail_from_name'  => 'Iconors',
            'mail_from_email' => 'noreply@iconors.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('log', app(SettingsService::class)->get('mail_driver'));
    }

    public function test_a_test_email_attempt_is_written_to_the_email_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Administrator');

        $this->actingAs($admin)->post(route('admin.settings.mail.test'), [
            'test_recipient' => 'someone@example.com',
        ]);

        // Brief §27: every attempt is logged, successful or not.
        $this->assertDatabaseHas('email_logs', [
            'recipient'    => 'someone@example.com',
            'template_key' => 'smtp_test',
        ]);
    }

    private function financeManager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Finance Manager');

        return $user;
    }

    private function paymentPayload(array $overrides = []): array
    {
        // Every gateway's mode is `required`, and the real form always submits
        // all of them because each is a <select> with a default. A new gateway
        // therefore has to be added here too, or every payment-settings test
        // fails validation on the missing field rather than on what it asserts.
        return array_merge([
            'stripe_mode' => 'test',
            'paypal_mode' => 'sandbox',
            'payoneer_mode' => 'sandbox',
        ], $overrides);
    }
}
