<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreatorApplication;
use App\Models\CreatorAuditLog;
use App\Models\CreatorEarning;
use App\Models\CreatorInformationRequest;
use App\Models\CreatorLedgerEntry;
use App\Models\CreatorPermission;
use App\Models\Movie;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\CreatorEarningsService;
use App\Services\CreatorIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreatorPlatformSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'api.enabled' => false,
            'creator.evidence_disk' => 'local',
        ]);
    }

    public function test_creator_cannot_read_another_creators_movie(): void
    {
        $owner = $this->creator();
        $attacker = $this->creator();
        $movie = Movie::factory()->create([
            'submitted_by' => $owner->id,
            'submission_origin' => 'creator',
            'publish_status' => 'draft',
            'is_active' => false,
        ]);

        $this->actingAs($attacker, 'sanctum')
            ->getJson("/api/v1/creator/movies/{$movie->id}")
            ->assertNotFound();
    }

    public function test_creator_draft_response_and_saved_wizard_step_have_a_stable_contract(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/creator/apply', [
                'creator_type' => 'vj',
                'application_mode' => 'claim',
                'display_name' => 'VJ Contract Test',
                'legal_name' => 'Contract Tester',
                'phone_number' => '+256700000000',
                'public_email' => 'creator-contract@example.test',
                'onboarding_step' => 3,
            ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.onboarding_step', 3)
            ->assertJsonPath('data.display_name', 'VJ Contract Test');

        $this->actingAs($user, 'sanctum')
            ->put('/api/v1/creator/application', [
                'creator_type' => 'vj',
                'application_mode' => 'claim',
                'display_name' => 'VJ Contract Test',
                'legal_name' => 'Contract Tester',
                'phone_number' => '+256700000000',
                'public_email' => 'creator-contract@example.test',
                'onboarding_step' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.onboarding_step', 5);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/creator/application')
            ->assertOk()
            ->assertJsonPath('data.onboarding_step', 5);
    }

    public function test_private_verification_evidence_is_owner_scoped(): void
    {
        Storage::fake('local');
        $owner = $this->creator();
        $attacker = $this->creator();

        $upload = $this->actingAs($owner, 'sanctum')
            ->post('/api/v1/creator/evidence', [
                'kind' => 'verification_video',
                'file' => UploadedFile::fake()->create('creator-proof.mp4', 512, 'video/mp4'),
            ]);

        $upload->assertCreated();
        $evidenceId = $upload->json('data.id');
        $path = "creator-evidence/{$owner->id}/{$owner->creatorApplication->id}";
        Storage::disk('local')->assertExists(
            collect(Storage::disk('local')->allFiles($path))->first()
        );

        $this->actingAs($attacker, 'sanctum')
            ->get("/api/v1/creator/evidence/{$evidenceId}")
            ->assertForbidden();

        $ownerDownload = $this->actingAs($owner, 'sanctum')
            ->get("/api/v1/creator/evidence/{$evidenceId}")
            ->assertOk();
        $this->assertStringContainsString('private', (string) $ownerDownload->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $ownerDownload->headers->get('Cache-Control'));
    }

    public function test_admin_can_approve_a_new_vj_before_the_creator_uploads_a_profile_photo(): void
    {
        $adminRole = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        \App\Models\Role::create(['name' => 'vj', 'display_name' => 'VJ']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $creator = $this->creator();
        $application = $creator->creatorApplication;
        $application->update([
            'status' => CreatorApplication::STATUS_UNDER_REVIEW,
            'display_name' => 'VJ Without Photo',
            'profile_image' => null,
        ]);
        $application->evidence()->create([
            'user_id' => $creator->id,
            'kind' => 'verification_video',
            'disk' => 'local',
            'path' => 'creator-evidence/test/video.mp4',
            'original_filename' => 'video.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'status' => 'submitted',
            'uploaded_at' => now(),
        ]);

        app(CreatorIdentityService::class)->approve($admin, $application);

        $this->assertDatabaseHas('vjs', [
            'user_id' => $creator->id,
            'name' => 'VJ Without Photo',
            'image' => null,
            'is_verified' => true,
        ]);
        $this->assertSame(CreatorApplication::STATUS_APPROVED, $application->fresh()->status);
    }

    public function test_approving_an_admin_as_a_vj_preserves_filament_access(): void
    {
        $adminRole = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $approver = User::factory()->create(['role_id' => $adminRole->id]);
        $creatorAdmin = User::factory()->create(['role_id' => $adminRole->id]);
        $application = CreatorApplication::create([
            'user_id' => $creatorAdmin->id,
            'creator_type' => 'vj',
            'application_mode' => 'create',
            'display_name' => 'VJ Administrator',
            'status' => CreatorApplication::STATUS_UNDER_REVIEW,
            'verification_status' => 'submitted',
        ]);
        $application->evidence()->create([
            'user_id' => $creatorAdmin->id,
            'kind' => 'verification_video',
            'disk' => 'local',
            'path' => 'creator-evidence/test/admin-video.mp4',
            'original_filename' => 'admin-video.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'status' => 'submitted',
            'uploaded_at' => now(),
        ]);

        app(CreatorIdentityService::class)->approve($approver, $application);

        $creatorAdmin->refresh();
        $this->assertSame($adminRole->id, $creatorAdmin->role_id);
        $this->assertTrue($creatorAdmin->isAdmin());
        $this->assertTrue($creatorAdmin->isVJ());
        $this->assertTrue($creatorAdmin->isVerifiedCreator());
        $this->assertDatabaseHas('vjs', [
            'user_id' => $creatorAdmin->id,
            'is_verified' => true,
        ]);
    }

    public function test_requested_information_can_be_saved_then_submitted_without_replacing_the_application(): void
    {
        Storage::fake('local');
        $adminRole = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $creator = $this->creator();
        $application = $creator->creatorApplication;
        $originalSubmittedAt = now()->subDay()->startOfSecond();
        $application->update([
            'status' => CreatorApplication::STATUS_INFORMATION_REQUIRED,
            'submitted_at' => $originalSubmittedAt,
            'onboarding_step' => 4,
        ]);
        $phone = CreatorInformationRequest::create([
            'user_id' => $creator->id,
            'creator_application_id' => $application->id,
            'field_label' => 'Official phone number',
            'instructions' => 'Add the number used for creator work.',
            'field_type' => 'phone',
            'step_key' => 'profile',
            'is_required' => true,
            'status' => 'open',
            'created_by' => $admin->id,
        ]);
        $proof = CreatorInformationRequest::create([
            'user_id' => $creator->id,
            'creator_application_id' => $application->id,
            'field_label' => 'Channel ownership proof',
            'instructions' => 'Upload a private screenshot.',
            'field_type' => 'image',
            'step_key' => 'ownership',
            'is_required' => true,
            'allowed_mime_types' => ['image/png'],
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/creator/information-requests/{$phone->id}/responses", [
                'value' => '+256700000001',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
        $this->actingAs($creator, 'sanctum')
            ->post("/api/v1/creator/information-requests/{$proof->id}/responses", [
                'file' => UploadedFile::fake()->image('channel-proof.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertSame(CreatorApplication::STATUS_INFORMATION_REQUIRED, $application->fresh()->status);
        $this->assertSame($originalSubmittedAt->toIso8601String(), $application->fresh()->submitted_at?->toIso8601String());

        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/creator/application/information-responses/submit')
            ->assertOk()
            ->assertJsonPath('data.status', CreatorApplication::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.request_progress.completed', 2);
        $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/creator/application/information-responses/submit')
            ->assertUnprocessable();

        $this->assertSame($application->id, $creator->creatorApplication()->value('id'));
        $this->assertSame($originalSubmittedAt->toIso8601String(), $application->fresh()->submitted_at?->toIso8601String());
        $this->assertNotNull($application->fresh()->resubmitted_at);
        $this->assertSame(2, CreatorInformationRequest::where('creator_application_id', $application->id)->where('status', 'submitted')->count());
        $this->assertDatabaseHas('creator_information_responses', ['information_request_id' => $phone->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('creator_information_responses', ['information_request_id' => $proof->id, 'status' => 'submitted']);
        $this->assertSame(2, \App\Models\CreatorInformationResponse::count());
        $this->assertTrue(CreatorAuditLog::where('event', 'creator.additional_information_submitted')->exists());
        $this->assertTrue(UserNotification::where('user_id', $admin->id)->where('type', 'creator_application_review_ready')->exists());
    }

    public function test_creator_cannot_save_a_response_for_another_users_request(): void
    {
        $adminRole = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $owner = $this->creator();
        $attacker = $this->creator();
        $informationRequest = CreatorInformationRequest::create([
            'user_id' => $owner->id,
            'creator_application_id' => $owner->creatorApplication->id,
            'field_label' => 'Private proof',
            'field_type' => 'long_text',
            'step_key' => 'verification',
            'is_required' => true,
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($attacker, 'sanctum')
            ->postJson("/api/v1/creator/information-requests/{$informationRequest->id}/responses", [
                'value' => 'This must not be stored.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('creator_information_responses', [
            'information_request_id' => $informationRequest->id,
            'user_id' => $attacker->id,
        ]);
    }

    public function test_ownership_declaration_is_recorded_without_publishing_the_draft(): void
    {
        $creator = $this->creator();
        $category = Category::factory()->create();

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/creator/movies', [
                'title' => 'Creator Owned Movie',
                'description' => 'A complete description that will later be reviewed.',
                'thumbnail_url' => 'https://media.example.test/poster.jpg',
                'category_id' => $category->id,
                'ownership_declaration' => true,
            ]);

        $response->assertCreated();
        $movie = Movie::findOrFail($response->json('data.id'));
        $this->assertNotNull($movie->ownership_declaration_accepted_at);
        $this->assertFalse($movie->is_active);
        $this->assertSame('draft', $movie->publish_status);
        $this->assertSame('draft', $movie->publication_status);
    }

    public function test_refund_reversal_is_append_only_and_idempotent(): void
    {
        $creator = User::factory()->create();
        $buyer = User::factory()->create();
        $gateway = PaymentGateway::create([
            'name' => 'Test gateway',
            'slug' => 'test-gateway',
            'code' => 'TEST',
            'display_name' => 'Test gateway',
            'is_active' => true,
        ]);
        $movie = Movie::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $buyer->id,
            'payment_gateway_id' => $gateway->id,
            'gateway_code' => 'TEST',
            'type' => 'BUY',
            'transactionable_type' => Movie::class,
            'transactionable_id' => $movie->id,
            'transaction_ref' => 'creator-refund-transaction',
            'external_reference' => 'provider-deposit-1',
            'amount' => '10000.00',
            'status' => 'SUCCESS',
        ]);
        $earning = CreatorEarning::create([
            'user_id' => $creator->id,
            'transaction_id' => $transaction->id,
            'earnable_type' => Movie::class,
            'earnable_id' => $movie->id,
            'gross_amount' => '10000.00',
            'commission_rate' => '30.00',
            'platform_amount' => '3000.00',
            'creator_amount' => '7000.00',
            'gross_amount_minor' => 10000,
            'net_amount_minor' => 10000,
            'creator_amount_minor' => 7000,
            'platform_amount_minor' => 3000,
            'creator_share_bps' => 7000,
            'platform_share_bps' => 3000,
            'eligibility_status' => 'creator_eligible',
            'calculation_snapshot' => ['gross_minor' => 10000],
            'idempotency_key' => 'earning-refund-test',
            'status' => 'pending',
            'available_at' => now()->addDay(),
        ]);
        app(\App\Services\CreatorWalletService::class)->post(
            $creator,
            7000,
            'purchase_earning',
            'pending',
            'earning-refund-test',
            PaymentTransaction::class,
            $transaction->id
        );

        $service = app(CreatorEarningsService::class);
        $service->reverseForTransaction($transaction, 5000, 'refund-provider-1');
        $service->reverseForTransaction($transaction, 5000, 'refund-provider-1');

        $reversalEntries = CreatorLedgerEntry::where('entry_type', 'earning_reversal')->get();
        $this->assertCount(2, $reversalEntries);
        $this->assertSame(-3500, (int) $reversalEntries->where('bucket', 'pending')->first()->amount_minor);
        $this->assertSame(3500, (int) $reversalEntries->where('bucket', 'reversed')->first()->amount_minor);
        $this->assertSame('pending', $earning->fresh()->status);
        $this->assertSame(3500, (int) app(\App\Services\CreatorWalletService::class)->balances($creator)['pending_minor']);
    }

    public function test_refund_webhook_requires_its_dedicated_secret(): void
    {
        config(['services.pawapay.refund_webhook_token' => 'refund-webhook-secret']);

        $this->postJson('/api/v1/webhooks/pawapay/refunds', [
            'depositId' => 'provider-deposit-1',
            'refundId' => 'refund-provider-1',
            'status' => 'COMPLETED',
            'amount' => 1000,
        ])->assertUnauthorized();
    }

    private function creator(): User
    {
        $user = User::factory()->create();
        $application = CreatorApplication::create([
            'user_id' => $user->id,
            'creator_type' => 'vj',
            'application_mode' => 'create',
            'display_name' => $user->name,
            'status' => CreatorApplication::STATUS_DRAFT,
            'verification_status' => 'not_submitted',
        ]);
        CreatorPermission::create([
            'user_id' => $user->id,
            'creator_application_id' => $application->id,
            'draft_submission_enabled' => true,
        ]);

        return $user->fresh();
    }
}
