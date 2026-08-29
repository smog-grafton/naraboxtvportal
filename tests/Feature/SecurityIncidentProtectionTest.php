<?php

namespace Tests\Feature;

use App\Events\PaymentSucceeded;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\ProtectedPayer;
use App\Models\SecurityIdentity;
use App\Models\SecurityRule;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\AccountSecurityService;
use App\Services\PaymentApprovalService;
use App\Services\PaymentSecurityService;
use App\Services\SecurityRuleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityIncidentProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('payment-security.minimum_seconds_between_attempts', 0);
        config()->set('payment-security.new_account.enabled', false);
    }

    #[Test]
    public function incident_token_rules_match_order_but_not_a_shared_surname(): void
    {
        $rule = SecurityRule::create([
            'name' => 'Test Ssemakula John', 'identity_type' => 'DISPLAY_NAME', 'matching_mode' => 'TOKEN_SET',
            'pattern' => 'Ssemakula John', 'action' => 'BLOCK_REGISTRATION', 'enabled' => true,
            'risk_level' => 'CRITICAL', 'reason' => 'Test incident rule.', 'priority' => 9999,
        ]);
        $service = app(SecurityRuleService::class);

        $this->assertTrue($service->matches($rule, 'John   Ssemakula'));
        $this->assertFalse($service->matches($rule, 'Hamza Ssemakula'));
    }

    #[Test]
    public function a_protected_payer_is_stopped_before_a_payment_row_or_gateway_controller(): void
    {
        $user = User::factory()->create(['name' => 'Legitimate Customer']);
        ProtectedPayer::create([
            'normalized_phone' => '256777546225', 'status' => 'ACTIVE',
            'reason' => 'OWNER_REPORTED_UNAUTHORIZED',
        ]);

        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256777546225'));

        $this->assertSame(403, $guard['response']?->getStatusCode());
        $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event_type' => 'PROTECTED_PAYER_ATTEMPT']);
    }

    #[Test]
    public function payment_restricted_and_suspended_users_are_stopped_before_transaction_creation(): void
    {
        foreach (['PAYMENT_RESTRICTED', 'SUSPENDED', 'BANNED'] as $status) {
            $user = User::factory()->create(['account_status' => $status]);
            $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770000'.random_int(100, 999)));
            $this->assertSame(403, $guard['response']?->getStatusCode());
            $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
        }
    }

    #[Test]
    public function active_subscription_blocks_a_duplicate_subscription_payment(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $plan = SubscriptionPlan::create(['name' => 'Security Test Plan', 'slug' => 'security-test-'.uniqid(), 'duration_days' => 7, 'price' => 8500, 'is_active' => true]);
        UserSubscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(6), 'status' => 'ACTIVE', 'auto_renew' => false,
        ]);

        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770100001', ['subscription_plan_id' => $plan->id]));

        $this->assertSame(422, $guard['response']?->getStatusCode());
        $this->assertSame(0, PaymentTransaction::where('user_id', $user->id)->count());
    }

    #[Test]
    public function a_banned_exact_identity_survives_local_user_deletion(): void
    {
        $user = User::factory()->create(['email' => 'persistent-block-'.uniqid().'@example.com']);
        $email = $user->email;
        app(AccountSecurityService::class)->ban($user, 'Confirmed abuse.', null, null, null);
        $user->delete();

        $this->assertDatabaseHas('security_identities', [
            'identity_type' => 'EMAIL', 'value_hash' => hash('sha256', strtolower($email)), 'action' => 'BLOCK_LOGIN',
        ]);
    }

    #[Test]
    public function sequential_nearby_payers_are_detected_without_blocking_a_prefix_globally(): void
    {
        $service = app(PaymentSecurityService::class);

        $this->assertTrue($service->looksSequential(['256777546225', '256777546223', '256777546222', '256777546220', '256777546219']));
        $this->assertFalse($service->looksSequential(['256777546225', '256772111111', '256701222222', '256758333333', '256787444444']));
        $this->assertSame(0, SecurityIdentity::where('identity_type', 'PAYER_PHONE')->where('normalized_value', 'like', '256777546%')->count());
    }

    #[Test]
    public function older_clients_without_a_device_header_can_still_reach_the_secure_pipeline(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770300001'));

        $this->assertNull($guard['response']);
        $guard['lock']?->release();
        $this->assertDatabaseHas('payment_attempts', ['user_id' => $user->id, 'allowed' => true, 'device_id' => null]);
    }

    #[Test]
    public function the_user_payment_rate_limit_is_enforced_before_a_gateway_call(): void
    {
        config()->set('payment-security.user.max_attempts', 2);
        $user = User::factory()->create(['created_at' => now()->subDay()]);
        $limiterKey = 'payment:user:'.hash('sha256', (string) $user->id);
        RateLimiter::clear($limiterKey);

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $guard = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770400001'));
                $this->assertNull($guard['response']);
                $guard['lock']?->release();
            }

            $blocked = app(PaymentSecurityService::class)->begin($this->paymentRequest($user, '256770400001'));
            $this->assertSame(429, $blocked['response']?->getStatusCode());
            $this->assertSame('PAYMENT_RATE_LIMITED', $blocked['response']?->getData(true)['code'] ?? null);
        } finally {
            RateLimiter::clear($limiterKey);
        }
    }

    #[Test]
    public function duplicate_access_grants_are_idempotent(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $user = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'Idempotency Plan', 'slug' => 'idempotency-'.uniqid(),
            'duration_days' => 7, 'price' => 8500, 'is_active' => true,
        ]);
        $gateway = PaymentGateway::create([
            'name' => 'Idempotency Gateway', 'slug' => 'idempotency-'.uniqid(),
            'code' => 'IDEMPOTENCY', 'display_name' => 'Idempotency Gateway', 'is_active' => true,
        ]);
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'payment_gateway_id' => $gateway->id,
            'gateway_code' => $gateway->code, 'type' => 'SUBSCRIPTION',
            'subscription_plan_id' => $plan->id, 'transaction_ref' => 'idempotency-'.uniqid(),
            'amount' => $plan->price, 'status' => 'SUCCESS',
        ]);

        PaymentApprovalService::grantAccess($transaction);
        $firstRenewalDate = $user->fresh()->renewal_date;
        PaymentApprovalService::grantAccess($transaction->fresh());

        $this->assertSame(1, UserSubscription::where('user_id', $user->id)->count());
        $this->assertTrue($user->fresh()->renewal_date->equalTo($firstRenewalDate));
        $this->assertNotNull($transaction->fresh()->access_granted_at);
        Event::assertDispatchedTimes(PaymentSucceeded::class, 1);
    }

    private function paymentRequest(User $user, string $phone, array $extra = []): Request
    {
        $request = Request::create('/api/v1/iotec/initiate', 'POST', array_merge([
            'type' => 'SUBSCRIPTION', 'phone' => $phone, 'method' => 'mobile_money',
        ], $extra), [], [], ['REMOTE_ADDR' => '198.51.100.'.random_int(2, 240)]);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
