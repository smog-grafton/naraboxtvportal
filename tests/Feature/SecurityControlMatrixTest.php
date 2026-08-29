<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\SecurityRule;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\AuthSessionService;
use App\Services\ClientIdentityService;
use App\Services\PaymentSecurityService;
use App\Services\RegistrationSecurityService;
use App\Services\RuntimeSecuritySettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityControlMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'api.enabled' => false,
            'api.require_verification_codes' => false,
            'security.registration.ip.max_attempts' => 100,
            'security.registration.email.max_attempts' => 100,
            'security.registration.device.max_attempts' => 100,
            'payment-security.minimum_seconds_between_attempts' => 0,
            'payment-security.new_account.enabled' => false,
            'payment-security.user.max_attempts' => 100,
            'payment-security.provider.max_attempts' => 100,
            'payment-security.ip.max_attempts' => 100,
            'payment-security.device.max_attempts' => 100,
            'payment-security.payer.max_attempts' => 100,
            'payment-security.unique_payers.max_per_user' => 100,
            'payment-security.unique_payers.max_per_provider' => 100,
            'payment-security.unique_payers.max_per_ip' => 100,
            'payment-security.unique_payers.max_per_device' => 100,
        ]);
        Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer']);
    }

    #[Test]
    public function a_normal_user_can_register(): void
    {
        Queue::fake();
        $email = 'normal-security-'.uniqid().'@example.com';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Normal Customer',
            'email' => $email,
            'password' => 'SafePassword123!',
            'password_confirmation' => 'SafePassword123!',
        ])->assertCreated()->assertJsonPath('data.is_new_user', true);

        $this->assertDatabaseHas('users', ['email' => $email, 'account_status' => 'ACTIVE']);
    }

    #[Test]
    public function an_existing_google_identity_authenticates_without_creating_a_duplicate_user(): void
    {
        $user = User::factory()->create(['email' => 'existing-google-'.uniqid().'@example.com']);
        $googleSub = 'google-existing-'.uniqid();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => $googleSub,
            'email' => $user->email,
        ]);
        $before = User::count();
        $this->mockGoogleUser($googleSub, $user->email, 'Existing Google Customer');

        $this->postJson('/api/v1/auth/google/mobile', ['access_token' => 'existing-token'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        $this->assertSame($before, User::count());
        $this->assertSame(1, SocialAccount::where('provider', 'google')->where('provider_user_id', $googleSub)->count());
    }

    #[Test]
    public function banned_email_and_google_identity_block_account_recreation_after_user_deletion(): void
    {
        $user = User::factory()->create(['email' => 'blocked-recreation-'.uniqid().'@example.com']);
        $googleSub = 'blocked-google-'.uniqid();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => $googleSub,
            'email' => $user->email,
        ]);
        app(AccountSecurityService::class)->ban($user, 'Confirmed test abuse.', null, null, null);
        $email = $user->email;
        $user->delete();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Recreated Account', 'email' => $email,
            'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!',
        ])->assertForbidden();

        $this->mockGoogleUser($googleSub, 'changed-'.uniqid().'@example.com', 'Changed Name');
        $this->postJson('/api/v1/auth/google/mobile', ['access_token' => 'blocked-token'])->assertForbidden();
    }

    #[Test]
    public function registration_ip_limiter_uses_the_resolved_client_ip(): void
    {
        config(['security.registration.ip.max_attempts' => 1]);
        $ip = '198.51.100.'.random_int(2, 240);
        $key = 'registration:ip:'.hash('sha256', $ip);
        RateLimiter::clear($key);
        $service = app(RegistrationSecurityService::class);

        try {
            $first = Request::create('/api/v1/auth/register', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);
            $second = Request::create('/api/v1/auth/register', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);
            $this->assertNull($service->registration($first, ['EMAIL' => 'first-'.uniqid().'@example.com']));
            $this->assertSame(429, $service->registration($second, ['EMAIL' => 'second-'.uniqid().'@example.com'])?->getStatusCode());
        } finally {
            RateLimiter::clear($key);
        }
    }

    public static function paymentLimiterDimensions(): array
    {
        return [['user'], ['provider'], ['ip'], ['device'], ['payer']];
    }

    #[Test]
    #[DataProvider('paymentLimiterDimensions')]
    public function every_payment_limiter_dimension_blocks_before_transaction_creation(string $dimension): void
    {
        config(["payment-security.{$dimension}.max_attempts" => 1]);
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $ip = '203.0.113.'.random_int(2, 240);
        $device = 'matrix-device-'.uniqid();
        $payer = '256770'.str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $providerSub = null;
        if ($dimension === 'provider') {
            $providerSub = 'matrix-google-'.uniqid();
            SocialAccount::create([
                'user_id' => $user->id, 'provider' => 'google',
                'provider_user_id' => $providerSub, 'email' => $user->email,
            ]);
        }
        $rawKey = match ($dimension) {
            'user' => (string) $user->id,
            'provider' => 'google:'.$providerSub,
            'ip' => $ip,
            'device' => $device,
            'payer' => $payer,
        };
        $key = 'payment:'.$dimension.':'.hash('sha256', $rawKey);
        RateLimiter::clear($key);

        try {
            $first = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, $payer, $ip, $device));
            $this->assertNull($first['response']);
            $first['lock']?->release();

            $second = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, $payer, $ip, $device));
            $this->assertSame(429, $second['response']?->getStatusCode());
            $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
        } finally {
            RateLimiter::clear($key);
        }
    }

    #[Test]
    public function cooldown_pending_and_atomic_lock_each_stop_duplicate_requests(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $request = $this->paymentRequest($user, '256770500001');

        $first = app(PaymentSecurityService::class)->begin($request);
        $this->assertNull($first['response']);
        $locked = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770500001'));
        $this->assertSame('PAYMENT_IN_PROGRESS', $locked['response']?->getData(true)['code'] ?? null);
        $first['lock']?->release();

        config(['payment-security.minimum_seconds_between_attempts' => 60]);
        $cooldownPayer = '256770500002';
        $cooldown = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, $cooldownPayer, '198.51.100.80', 'cooldown-device'));
        $this->assertNull($cooldown['response']);
        $cooldown['lock']?->release();
        $cooldownBlocked = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, $cooldownPayer, '198.51.100.80', 'cooldown-device'));
        $this->assertSame('PAYMENT_COOLDOWN', $cooldownBlocked['response']?->getData(true)['code'] ?? null);
        $cooldownBlocked['lock']?->release();

        config(['payment-security.minimum_seconds_between_attempts' => 0]);
        $gateway = $this->gateway();
        PaymentTransaction::create([
            'user_id' => $user->id, 'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code, 'type' => 'SUBSCRIPTION',
            'transaction_ref' => 'pending-'.uniqid(), 'amount' => 5000, 'status' => 'PENDING',
        ]);
        $pending = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770500003'));
        $this->assertSame('PENDING_PAYMENT_EXISTS', $pending['response']?->getData(true)['code'] ?? null);
    }

    #[Test]
    public function unique_payer_rotation_and_enumeration_create_events_and_restrict_payment(): void
    {
        config([
            'payment-security.unique_payers.max_per_user' => 2,
            'payment-security.enumeration.minimum_unique_numbers' => 50,
        ]);
        $rotator = User::factory()->create(['created_at' => now()->subDay()]);
        foreach (['256770600001', '256770600100', '256770600200'] as $phone) {
            $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($rotator, $phone));
            $guard['lock']?->release();
        }
        $this->assertSame('PAYMENT_RESTRICTED', $rotator->fresh()->account_status);
        $this->assertDatabaseHas('security_events', ['user_id' => $rotator->id, 'event_type' => 'UNIQUE_PAYER_THRESHOLD']);

        config([
            'payment-security.unique_payers.max_per_user' => 100,
            'payment-security.enumeration.minimum_unique_numbers' => 5,
        ]);
        $enumerator = User::factory()->create(['created_at' => now()->subDay()]);
        foreach (['256777546225', '256777546223', '256777546222', '256777546220', '256777546219'] as $phone) {
            $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($enumerator, $phone, '198.51.100.31'));
            $guard['lock']?->release();
        }
        $this->assertSame('PAYMENT_RESTRICTED', $enumerator->fresh()->account_status);
        $this->assertDatabaseHas('security_events', ['user_id' => $enumerator->id, 'event_type' => 'PHONE_ENUMERATION_DETECTED']);
    }

    #[Test]
    public function enumeration_is_correlated_across_recreated_accounts_by_provider_and_device(): void
    {
        config(['payment-security.enumeration.minimum_unique_numbers' => 5]);
        $providerSub = 'recreated-provider-'.uniqid();
        $device = 'recreated-device-'.uniqid();
        $phones = ['256777546225', '256777546223', '256777546222', '256777546220', '256777546219'];
        $firstUser = User::factory()->create(['created_at' => now()->subDay()]);
        SocialAccount::create(['user_id' => $firstUser->id, 'provider' => 'google', 'provider_user_id' => $providerSub, 'email' => $firstUser->email]);
        foreach (array_slice($phones, 0, 4) as $phone) {
            $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($firstUser, $phone, '198.51.100.90', $device));
            $guard['lock']?->release();
        }
        $firstUser->delete();

        $secondUser = User::factory()->create(['created_at' => now()->subDay()]);
        SocialAccount::create(['user_id' => $secondUser->id, 'provider' => 'google', 'provider_user_id' => $providerSub, 'email' => $secondUser->email]);
        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($secondUser, $phones[4], '198.51.100.91', $device));
        $guard['lock']?->release();

        $this->assertSame(403, $guard['response']?->getStatusCode());
        $this->assertSame('PAYMENT_RESTRICTED', $secondUser->fresh()->account_status);
    }

    #[Test]
    public function disabled_payments_and_lockdown_stop_before_a_transaction(): void
    {
        $settings = app(RuntimeSecuritySettings::class);
        $user = User::factory()->create(['created_at' => now()->subDay()]);

        try {
            $settings->set('payments_enabled', false);
            $disabled = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770700001'));
            $this->assertSame('PAYMENTS_DISABLED', $disabled['response']?->getData(true)['code'] ?? null);

            $settings->set('payments_enabled', true);
            $settings->set('lockdown_mode', true);
            $lockedPayment = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770700002'));
            $lockedRegistration = app(RegistrationSecurityService::class)->registration(
                Request::create('/api/v1/auth/register', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.100']),
                ['EMAIL' => 'lockdown-'.uniqid().'@example.com']
            );
            $this->assertSame('PAYMENTS_DISABLED', $lockedPayment['response']?->getData(true)['code'] ?? null);
            $this->assertSame('REGISTRATION_DISABLED', $lockedRegistration?->getData(true)['code'] ?? null);
            $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
        } finally {
            $settings->set('payments_enabled', true);
            $settings->set('lockdown_mode', false);
        }
    }

    #[Test]
    public function a_new_account_that_hammers_payments_is_restricted_before_the_gateway(): void
    {
        config([
            'payment-security.new_account.enabled' => true,
            'payment-security.new_account.max_attempts' => 1,
            'payment-security.new_account.window_minutes' => 30,
        ]);
        $user = User::factory()->create(['created_at' => now()]);
        PaymentAttempt::create([
            'user_id' => $user->id, 'ip_address' => '198.51.100.120',
            'payer_phone' => '256770710001', 'gateway' => 'iotec',
            'payment_type' => 'SUBSCRIPTION', 'allowed' => true,
            'decision_code' => 'ALLOWED', 'occurred_at' => now(),
        ]);

        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770710002', '198.51.100.120'));

        $this->assertSame('NEW_ACCOUNT_PAYMENT_LIMIT', $guard['response']?->getData(true)['code'] ?? null);
        $this->assertSame('PAYMENT_RESTRICTED', $user->fresh()->account_status);
        $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
        $guard['lock']?->release();
    }

    #[Test]
    public function sanctum_partner_and_force_logout_cannot_bypass_account_enforcement(): void
    {
        $user = User::factory()->create(['account_status' => 'PAYMENT_RESTRICTED']);
        Partner::create([
            'user_id' => $user->id, 'display_name' => 'Restricted Partner',
            'slug' => 'restricted-'.uniqid(), 'referral_code' => strtoupper(substr(uniqid(), -8)),
            'status' => 'active',
        ]);
        $plainToken = $user->createToken('security-matrix')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/v1/iotec/initiate', ['type' => 'SUBSCRIPTION', 'phone' => '256770800001'])
            ->assertForbidden();
        $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());

        app(AccountSecurityService::class)->revokeSessions($user);
        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function a_suspended_sessions_refresh_token_cannot_mint_a_replacement_session(): void
    {
        $user = User::factory()->create();
        $session = app(AuthSessionService::class)->issue($user, 'app');
        app(AccountSecurityService::class)->suspend($user, 'Security test suspension.', null, now()->addHour(), null);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
        $this->assertSame(0, $user->tokens()->count());
    }

    #[Test]
    public function the_final_active_administrator_cannot_be_banned(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
        User::where('role_id', $adminRole->id)->update(['account_status' => 'SUSPENDED']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'account_status' => 'ACTIVE']);

        $this->expectException(InvalidArgumentException::class);
        app(AccountSecurityService::class)->ban($admin, 'Must be rejected.', null, null, null);
    }

    #[Test]
    public function an_authorized_restore_reenables_the_account_and_ends_ban_identities(): void
    {
        $user = User::factory()->create(['email' => 'restore-'.uniqid().'@example.com']);
        $service = app(AccountSecurityService::class);
        $service->ban($user, 'Confirmed test ban.', null, null, null);
        $service->activate($user, 'Investigation cleared the account.', null, null);

        $this->assertSame('ACTIVE', $user->fresh()->account_status);
        $this->assertDatabaseHas('security_identities', [
            'identity_type' => 'EMAIL',
            'value_hash' => hash('sha256', strtolower($user->email)),
            'source' => 'ACCOUNT_BAN',
            'enabled' => false,
        ]);
    }

    #[Test]
    public function forwarded_client_ip_is_only_accepted_from_an_explicit_trusted_proxy(): void
    {
        config(['security.trusted_proxies' => ['10.0.0.0/8']]);
        $resolver = app(ClientIdentityService::class);
        $untrusted = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.10', 'HTTP_CF_CONNECTING_IP' => '203.0.113.50']);
        $trusted = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.1.2.3', 'HTTP_CF_CONNECTING_IP' => '203.0.113.50']);

        $this->assertSame('198.51.100.10', $resolver->ip($untrusted));
        $this->assertSame('203.0.113.50', $resolver->ip($trusted));
    }

    #[Test]
    public function cidr_and_alert_only_rules_are_supported_without_blocking_the_request(): void
    {
        SecurityRule::create([
            'name' => 'CIDR alert', 'identity_type' => 'CIDR', 'matching_mode' => 'NORMALIZED_EXACT',
            'pattern' => '198.51.100.0/24', 'action' => 'ALERT_ONLY', 'enabled' => true,
            'risk_level' => 'WATCH', 'reason' => 'Test alert.', 'priority' => 9999,
        ]);
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770900001', '198.51.100.55'));

        $this->assertNull($guard['response']);
        $guard['lock']?->release();
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event_type' => 'SECURITY_RULE_ALERT']);
    }

    #[Test]
    public function payment_webhooks_fail_closed_without_valid_authentication(): void
    {
        config([
            'api.enabled' => true,
            'api.key' => 'client-key-that-providers-do-not-have',
            'services.flutterwave.webhook_secret_hash' => 'test-flutterwave-secret',
            'services.pawapay.webhook_token' => null,
            'services.pawapay.verify_callback_signature' => true,
            'services.iotec.webhook_token' => 'test-iotec-secret',
        ]);

        $this->postJson('/api/v1/flutterwave/webhook', ['data' => ['tx_ref' => 'unknown', 'id' => 1]])
            ->assertUnauthorized()->assertJsonPath('error', 'Invalid webhook signature');
        $this->postJson('/api/v1/webhooks/pawapay/deposits', ['depositId' => 'unknown'])
            ->assertUnauthorized()->assertJsonPath('error', 'Invalid callback signature');
        $this->postJson('/api/v1/iotec/webhook', ['id' => 'unknown', 'status' => 'success'])
            ->assertUnauthorized()->assertJsonPath('error', 'Invalid webhook credentials');
    }

    private function paymentRequest(User $user, string $phone, string $ip = '198.51.100.30', ?string $device = null): Request
    {
        $server = ['REMOTE_ADDR' => $ip];
        if ($device) {
            $server['HTTP_X_NBX_DEVICE_ID'] = $device;
        }
        $request = Request::create('/api/v1/iotec/initiate', 'POST', [
            'type' => 'SUBSCRIPTION', 'phone' => $phone, 'method' => 'mobile_money',
        ], [], [], $server);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function gateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'name' => 'Security Matrix Gateway', 'slug' => 'matrix-'.uniqid(),
            'code' => 'MATRIX', 'display_name' => 'Security Matrix Gateway', 'is_active' => true,
        ]);
    }

    private function mockGoogleUser(string $sub, string $email, string $name): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getId')->andReturn($sub);
        $googleUser->shouldReceive('getName')->andReturn($name);
        $googleUser->shouldReceive('getAvatar')->andReturn(null);
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('userFromToken')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
