<?php

namespace Tests\Feature;

use App\Mail\DynamicMail;
use App\Models\CreatorApplication;
use App\Models\CreatorNotificationDelivery;
use App\Models\CreatorPayoutOption;
use App\Models\CreatorPermission;
use App\Models\EmailTemplate;
use App\Models\FinancialSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VJ;
use App\Models\VJClaimRequest;
use App\Services\CreatorCommunicationService;
use Database\Seeders\CreatorPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatorAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_platform_seeder_is_idempotent_and_conservative(): void
    {
        $this->seed(CreatorPlatformSeeder::class);
        $this->seed(CreatorPlatformSeeder::class);

        $settings = FinancialSetting::current();
        $this->assertSame(7000, (int) $settings->creator_share_bps);
        $this->assertSame(3000, (int) $settings->platform_share_bps);
        $this->assertSame(4000, (int) $settings->subscription_pool_bps);
        $this->assertTrue($settings->manual_payout_enabled);
        $this->assertFalse($settings->auto_payout_enabled);

        $this->assertSame(5, CreatorPayoutOption::count());
        $this->assertDatabaseHas('creator_payout_options', [
            'key' => 'iotec',
            'automation_type' => 'automatic',
            'is_enabled' => false,
        ]);
        $this->assertDatabaseHas('creator_payout_options', [
            'key' => 'mtn_mobile_money_manual',
            'automation_type' => 'manual',
            'is_enabled' => true,
        ]);
        $this->assertSame(
            EmailTemplate::query()->count(),
            EmailTemplate::query()->distinct()->count('name')
        );
        $this->assertDatabaseHas('email_templates', ['name' => 'creator_application_approved']);
        $this->assertDatabaseHas('email_templates', ['name' => 'creator_content_published']);
        $this->assertDatabaseHas('email_templates', ['name' => 'creator_withdrawal_paid']);
    }

    public function test_capabilities_drive_existing_security_gates(): void
    {
        $user = User::factory()->create();
        $permission = CreatorPermission::create([
            'user_id' => $user->id,
            'permission_preset' => 'custom',
            'capabilities' => [
                'create_movies',
                'set_rental_price',
                'request_withdrawal',
                'manage_public_profile',
            ],
            'identity_verified' => true,
        ]);

        $this->assertTrue($permission->fresh()->draft_submission_enabled);
        $this->assertTrue($permission->fresh()->monetization_enabled);
        $this->assertTrue($permission->fresh()->withdrawals_enabled);
        $this->assertTrue($permission->fresh()->profile_editing_enabled);
        $this->assertTrue($permission->fresh()->allows('request_withdrawal'));
        $this->assertFalse($permission->fresh()->allows('publish_without_review'));

        $permission->update(['is_suspended' => true, 'restriction_reason' => 'Test hold']);
        $this->assertFalse($permission->fresh()->allows('create_movies'));
        $this->assertFalse($permission->fresh()->allows('request_withdrawal'));
    }

    public function test_unconfigured_automatic_payout_option_cannot_be_enabled(): void
    {
        $option = CreatorPayoutOption::create([
            'key' => 'future_automatic_provider',
            'label' => 'Future provider',
            'category' => 'mobile_money',
            'automation_type' => 'automatic',
            'is_configured' => false,
            'configuration_status' => 'incomplete',
            'is_enabled' => false,
        ]);

        $this->expectException(ValidationException::class);
        $option->update(['is_enabled' => true]);
    }

    public function test_financial_shares_must_total_one_hundred_percent(): void
    {
        $this->expectException(ValidationException::class);
        FinancialSetting::create([
            'creator_share_bps' => 6500,
            'platform_share_bps' => 3000,
            'subscription_pool_bps' => 4000,
            'auto_payout_enabled' => false,
        ]);
    }

    public function test_creator_communications_are_idempotent_across_email_and_in_app(): void
    {
        Mail::fake();
        $this->seed(CreatorPlatformSeeder::class);
        $user = User::factory()->create(['email' => 'creator@example.test']);
        $service = app(CreatorCommunicationService::class);

        $arguments = [
            $user,
            'creator-test-event:1',
            'creator_application_submitted',
            'Creator application received',
            'Your application is ready for review.',
            '/creator/verification',
        ];
        $service->notify(...$arguments);
        $service->notify(...$arguments);

        $this->assertSame(1, CreatorNotificationDelivery::count());
        $this->assertSame(1, UserNotification::where('user_id', $user->id)->count());
        $notification = UserNotification::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('creator_application_submitted', $notification->data['notification_type']);
        $this->assertSame('/creator/verification', $notification->data['action']['url']);
        Mail::assertQueued(DynamicMail::class, 1);
    }

    public function test_creator_administration_pages_render_in_filament(): void
    {
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
        ]);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($admin);
        $this->seed(CreatorPlatformSeeder::class);
        $creator = User::factory()->create();
        $application = CreatorApplication::create([
            'user_id' => $creator->id,
            'creator_type' => 'vj',
            'application_mode' => 'claim',
            'display_name' => 'Creator Test',
            'status' => CreatorApplication::STATUS_SUBMITTED,
            'verification_status' => 'evidence_submitted',
        ]);
        $vj = VJ::create([
            'name' => 'Claimable VJ',
            'slug' => 'claimable-vj',
            'image' => 'vjs/claimable.jpg',
        ]);
        $claim = VJClaimRequest::create([
            'user_id' => $creator->id,
            'vj_id' => $vj->id,
            'status' => 'pending',
        ]);

        foreach ([
            '/admin/creator-permissions',
            '/admin/creator-permissions/create',
            '/admin/creator-payout-methods',
            '/admin/creator-payout-accounts',
            '/admin/creator-settlements',
            '/admin/creator-wallets',
            '/admin/v-j-claim-requests',
            '/admin/creator-applications',
            '/admin/media-libraries',
            '/admin/financial-settings',
        ] as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), "Filament page failed to render: {$url}");
        }

        $this->get('/admin/financial-settings/'.FinancialSetting::current()->id.'/edit')->assertOk();
        $this->get("/admin/creator-applications/{$application->id}")->assertOk();
        $this->get("/admin/v-j-claim-requests/{$claim->id}")->assertOk();
    }
}
