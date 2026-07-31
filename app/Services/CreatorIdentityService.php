<?php

namespace App\Services;

use App\Models\CreatorApplication;
use App\Models\CreatorClaim;
use App\Models\CreatorPermission;
use App\Models\CreatorProfileOwnershipHistory;
use App\Models\CreatorVerificationEvidence;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Models\VJ;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatorIdentityService
{
    public function __construct(
        private readonly CreatorAuditService $audit,
        private readonly CreatorCommunicationService $communications
    ) {}

    public function saveDraft(User $user, array $attributes): CreatorApplication
    {
        // Query directly instead of trusting a possibly cached hasOne relation.
        // This also prevents repeated draft saves in one session from creating
        // separate applications.
        $application = CreatorApplication::query()->firstOrNew(['user_id' => $user->id]);
        if ($application->exists && ! $application->canBeEdited()) {
            throw ValidationException::withMessages([
                'application' => ['This application is locked while it is being reviewed.'],
            ]);
        }

        $displayName = $attributes['display_name'];
        $shouldRefreshChallenge = ! $application->challenge_phrase
            || ! $application->challenge_expires_at
            || $application->challenge_expires_at->isPast()
            || $application->display_name !== $displayName;
        $challenge = [];
        if ($shouldRefreshChallenge) {
            $code = (string) random_int(1000, 9999);
            $challenge = [
                'challenge_phrase' => "My name is {$displayName}, and I am verifying my creator account for NaraBox TV. My verification code is {$code}.",
                'challenge_code_hash' => Hash::make($code),
                'challenge_expires_at' => now()->addHours((int) config('creator.verification_challenge_hours', 72)),
            ];
        }

        $application->fill(array_merge([
            'creator_type' => $attributes['creator_type'],
            'application_mode' => $attributes['application_mode'] ?? 'create',
            'onboarding_step' => max(1, min(6, (int) ($attributes['onboarding_step'] ?? $application->onboarding_step ?? 1))),
            'display_name' => $displayName,
            'legal_name' => $attributes['legal_name'] ?? null,
            'phone_number' => $attributes['phone_number'] ?? null,
            'public_email' => $attributes['public_email'] ?? null,
            'website_url' => $attributes['website_url'] ?? null,
            'social_links' => $attributes['social_links'] ?? null,
            'bio' => $attributes['bio'] ?? null,
            'genres' => $attributes['genres'] ?? null,
            'status' => $application->exists
                && in_array($application->status, [CreatorApplication::STATUS_INFORMATION_REQUIRED, 'needs_changes'], true)
                    ? $application->status
                    : CreatorApplication::STATUS_DRAFT,
            'verification_status' => $application->exists
                ? ($application->verification_status ?: 'not_submitted')
                : 'not_submitted',
        ], $challenge));
        $application->save();

        CreatorPermission::firstOrCreate(
            ['user_id' => $user->id],
            [
                'creator_application_id' => $application->id,
                'permission_preset' => 'applicant',
                'capabilities' => config('creator.permission_presets.applicant', []),
                'draft_submission_enabled' => true,
            ]
        );

        $this->audit->record('creator.application_draft_saved', $user, $user, $application);

        return $application->refresh();
    }

    public function startClaim(User $user, CreatorApplication $application, array $attributes): CreatorClaim
    {
        if ($application->user_id !== $user->id) {
            throw ValidationException::withMessages(['claim' => ['Application ownership does not match.']]);
        }

        $claimableClass = $attributes['claimable_type'] === 'vj' ? VJ::class : MediaLibrary::class;
        $claimable = $claimableClass::find($attributes['claimable_id']);
        if (! $claimable) {
            throw ValidationException::withMessages(['claimable_id' => ['The creator profile does not exist.']]);
        }

        $activeClaim = CreatorClaim::query()
            ->where('user_id', $user->id)
            ->where('claimable_type', $claimableClass)
            ->where('claimable_id', $claimable->id)
            ->whereNotIn('status', ['rejected', 'revoked'])
            ->first();
        if ($activeClaim) {
            return $activeClaim;
        }

        $code = (string) random_int(1000, 9999);
        $creatorName = (string) ($claimable->name ?? $application->display_name);
        $phrase = "My name is {$creatorName}, and I am verifying my creator account for NaraBox TV. My verification code is {$code}.";

        $claim = CreatorClaim::create([
            'user_id' => $user->id,
            'creator_application_id' => $application->id,
            'claimable_type' => $claimableClass,
            'claimable_id' => $claimable->id,
            'status' => 'draft',
            'legal_name' => $attributes['legal_name'] ?? $application->legal_name,
            'creator_name' => $creatorName,
            'phone_number' => $attributes['phone_number'] ?? $application->phone_number,
            'email' => $attributes['email'] ?? $user->email,
            'official_social_url' => $attributes['official_social_url'] ?? null,
            'telegram_url' => $attributes['telegram_url'] ?? null,
            'youtube_url' => $attributes['youtube_url'] ?? null,
            'facebook_url' => $attributes['facebook_url'] ?? null,
            'tiktok_url' => $attributes['tiktok_url'] ?? null,
            'existing_business_contact' => $attributes['existing_business_contact'] ?? null,
            'sample_work_url' => $attributes['sample_work_url'] ?? null,
            'relationship_explanation' => $attributes['relationship_explanation'],
            'verification_method' => $attributes['verification_method'] ?? 'private_video',
            'challenge_phrase' => $phrase,
            'challenge_code_hash' => Hash::make($code),
            'challenge_expires_at' => now()->addHours((int) config('creator.verification_challenge_hours', 72)),
        ]);

        $application->update([
            'application_mode' => 'claim',
            'claimable_type' => $claimableClass,
            'claimable_id' => $claimable->id,
            'challenge_phrase' => $phrase,
            'challenge_code_hash' => $claim->challenge_code_hash,
            'challenge_expires_at' => $claim->challenge_expires_at,
        ]);

        $this->audit->record('creator.claim_started', $user, $user, $claim);

        return $claim;
    }

    public function storeEvidence(
        User $user,
        CreatorApplication $application,
        UploadedFile $file,
        string $kind,
        ?CreatorClaim $claim = null
    ): CreatorVerificationEvidence {
        if ($application->user_id !== $user->id || ($claim && $claim->user_id !== $user->id)) {
            throw ValidationException::withMessages(['evidence' => ['You cannot upload evidence for another creator.']]);
        }

        $disk = (string) config('creator.evidence_disk', 'local');
        $directory = 'creator-evidence/'.$user->id.'/'.$application->id;
        $storedName = Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs($directory, $storedName, $disk);
        if (! $path) {
            throw ValidationException::withMessages(['evidence' => ['The private evidence upload could not be stored.']]);
        }

        $evidence = CreatorVerificationEvidence::create([
            'user_id' => $user->id,
            'creator_application_id' => $application->id,
            'creator_claim_id' => $claim?->id,
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'status' => 'submitted',
            'uploaded_at' => now(),
            'retention_until' => now()->addDays((int) config('creator.evidence_retention_days', 90)),
        ]);
        CreatorVerificationEvidence::query()
            ->where('user_id', $user->id)
            ->where('creator_application_id', $application->id)
            ->where('kind', $kind)
            ->whereKeyNot($evidence->id)
            ->where('status', 'submitted')
            ->update(['status' => 'superseded']);

        if (in_array($kind, ['verification_video', 'channel_challenge'], true)) {
            $application->update(['verification_status' => 'evidence_submitted']);
        }
        $this->audit->record('creator.evidence_uploaded', $user, $user, $evidence, [], [
            'kind' => $kind,
            'mime_type' => $evidence->mime_type,
            'size_bytes' => $evidence->size_bytes,
        ]);

        return $evidence;
    }

    public function submit(User $user, CreatorApplication $application): CreatorApplication
    {
        if ($application->user_id !== $user->id || ! $application->canBeEdited()) {
            throw ValidationException::withMessages(['application' => ['This application cannot be submitted.']]);
        }

        if ($application->application_mode === 'claim') {
            $claim = $application->claims()->latest()->first();
            if (! $claim) {
                throw ValidationException::withMessages(['claim' => ['Complete the creator-profile claim first.']]);
            }
            $hasEvidence = $claim->evidence()->where('status', 'submitted')->exists();
            if ($claim->verification_method === 'private_video' && ! $hasEvidence) {
                throw ValidationException::withMessages(['verification_video' => ['Upload the private verification video before submitting.']]);
            }
            $claim->update(['status' => 'submitted', 'submitted_at' => now()]);
        } elseif (! $application->evidence()
            ->where('kind', 'verification_video')
            ->where('status', 'submitted')
            ->exists()) {
            throw ValidationException::withMessages([
                'verification_video' => ['Upload the private verification video before submitting.'],
            ]);
        }

        $unanswered = $application->informationRequests()
            ->where('is_required', true)
            ->where('status', 'open')
            ->whereDoesntHave('responses', fn ($query) => $query->where('status', 'submitted'))
            ->exists();
        if ($unanswered) {
            throw ValidationException::withMessages(['additional_information' => ['Respond to all required information requests first.']]);
        }

        $wasResubmission = in_array($application->status, [
            CreatorApplication::STATUS_INFORMATION_REQUIRED,
            'needs_changes',
        ], true);
        $application->update([
            'status' => $wasResubmission
                ? CreatorApplication::STATUS_UNDER_REVIEW
                : CreatorApplication::STATUS_SUBMITTED,
            'submitted_at' => $application->submitted_at ?: now(),
            'resubmitted_at' => $wasResubmission ? now() : $application->resubmitted_at,
            'rejection_reason' => null,
        ]);
        $this->audit->record(
            $wasResubmission ? 'creator.application_resubmitted' : 'creator.application_submitted',
            $user,
            $user,
            $application
        );
        $isClaim = $application->application_mode === 'claim';
        $this->communications->notify(
            $user,
            "creator-application:{$application->id}:submitted",
            $isClaim ? 'creator_claim_submitted' : 'creator_application_submitted',
            $isClaim ? 'Creator profile claim received' : 'Creator application received',
            $isClaim
                ? 'Your creator profile claim is ready for review.'
                : 'Your application is ready for review. We will notify you if more information is needed.',
            '/creator/application',
            [
                'application_id' => $application->id,
                'action_label' => 'View application',
                'creator_name' => $application->display_name,
            ],
            $wasResubmission ? 'creator_application_under_review' : 'creator_application_submitted'
        );

        return $application->refresh();
    }

    public function approve(
        User $admin,
        CreatorApplication $application,
        bool $publishingEnabled = false,
        bool $monetizationEnabled = false,
        bool $withdrawalsEnabled = false
    ): CreatorApplication {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['approval' => ['Only an administrator may approve creator identity.']]);
        }

        return DB::transaction(function () use ($admin, $application, $publishingEnabled, $monetizationEnabled, $withdrawalsEnabled) {
            $application = CreatorApplication::query()->lockForUpdate()->findOrFail($application->id);
            $user = User::query()->lockForUpdate()->findOrFail($application->user_id);
            $profile = null;
            if (! in_array($application->status, [
                CreatorApplication::STATUS_SUBMITTED,
                CreatorApplication::STATUS_UNDER_REVIEW,
            ], true)) {
                throw ValidationException::withMessages(['approval' => ['Only submitted applications under review can be approved.']]);
            }

            if ($application->application_mode === 'claim') {
                $claim = $application->claims()->where('status', 'submitted')->lockForUpdate()->latest()->first();
                if (! $claim) {
                    throw ValidationException::withMessages(['claim' => ['A submitted claim is required.']]);
                }
                if ($claim->verification_method === 'private_video'
                    && ! $claim->evidence()->where('kind', 'verification_video')->where('status', 'submitted')->exists()) {
                    throw ValidationException::withMessages(['approval' => ['Reviewable private verification video evidence is missing.']]);
                }
                $profile = $claim->claimable()->first();
                if (! $profile) {
                    throw ValidationException::withMessages(['claim' => ['Claimed profile no longer exists.']]);
                }

                $oldUserId = $profile->user_id;
                $profile->update(['user_id' => $user->id, 'is_verified' => true]);
                CreatorProfileOwnershipHistory::create([
                    'profile_type' => $profile::class,
                    'profile_id' => $profile->id,
                    'previous_user_id' => $oldUserId,
                    'new_user_id' => $user->id,
                    'claim_id' => $claim->id,
                    'action' => $oldUserId && $oldUserId !== $user->id ? 'transferred' : 'claim_linked',
                    'reason' => 'Creator identity claim approved after evidence review.',
                    'actor_id' => $admin->id,
                    'occurred_at' => now(),
                ]);
                $claim->update([
                    'status' => 'approved',
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);
                $this->audit->record('creator.claim_approved', $admin, $user, $claim, [
                    'previous_user_id' => $oldUserId,
                ], [
                    'user_id' => $user->id,
                ]);
            } else {
                if (! $application->evidence()
                    ->where('kind', 'verification_video')
                    ->where('status', 'submitted')
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'approval' => ['Reviewable private verification video evidence is missing.'],
                    ]);
                }
                $profile = $application->creator_type === 'vj'
                    ? VJ::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'name' => $application->display_name,
                            'bio' => $application->bio,
                            'image' => $application->profile_image,
                            'is_active' => true,
                            'is_verified' => true,
                        ]
                    )
                    : MediaLibrary::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'name' => $application->display_name,
                            'bio' => $application->bio,
                            'image' => $application->profile_image,
                            'is_active' => true,
                            'is_verified' => true,
                        ]
                    );
                CreatorProfileOwnershipHistory::firstOrCreate(
                    [
                        'profile_type' => $profile::class,
                        'profile_id' => $profile->id,
                        'new_user_id' => $user->id,
                        'action' => 'profile_created',
                    ],
                    [
                        'previous_user_id' => null,
                        'actor_id' => $admin->id,
                        'reason' => 'New creator profile approved.',
                        'occurred_at' => now(),
                    ]
                );
            }

            $application->approve($admin);
            $capabilities = config('creator.permission_presets.standard', []);
            if ($publishingEnabled) {
                $capabilities[] = 'publish_without_review';
            }
            if ($monetizationEnabled) {
                $capabilities = array_merge($capabilities, [
                    'set_rental_price',
                    'set_purchase_price',
                    'select_subscription_plans',
                ]);
            }
            if ($withdrawalsEnabled && $monetizationEnabled) {
                $capabilities[] = 'request_withdrawal';
            }
            $capabilities = array_values(array_unique($capabilities));
            $preset = $publishingEnabled && $monetizationEnabled && $withdrawalsEnabled
                ? 'publisher'
                : ($monetizationEnabled && $withdrawalsEnabled ? 'monetized' : ($monetizationEnabled || $publishingEnabled ? 'custom' : 'standard'));

            CreatorPermission::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'creator_application_id' => $application->id,
                    'permission_preset' => $preset,
                    'capabilities' => $capabilities,
                    'identity_verified' => true,
                    'draft_submission_enabled' => true,
                    'profile_editing_enabled' => true,
                    'publishing_enabled' => $publishingEnabled,
                    'monetization_enabled' => $monetizationEnabled,
                    'withdrawals_enabled' => $withdrawalsEnabled && $monetizationEnabled,
                    'is_suspended' => false,
                    'is_revoked' => false,
                    'approved_by' => $admin->id,
                    'identity_verified_at' => now(),
                    'publishing_enabled_at' => $publishingEnabled ? now() : null,
                    'monetization_enabled_at' => $monetizationEnabled ? now() : null,
                    'withdrawals_enabled_at' => $withdrawalsEnabled && $monetizationEnabled ? now() : null,
                ]
            );
            $this->audit->record('creator.identity_approved', $admin, $user, $application, [], [
                'profile_type' => $profile::class,
                'profile_id' => $profile->id,
                'publishing_enabled' => $publishingEnabled,
                'monetization_enabled' => $monetizationEnabled,
                'withdrawals_enabled' => $withdrawalsEnabled && $monetizationEnabled,
            ]);
            $this->communications->notify(
                $user,
                "creator-application:{$application->id}:approved",
                'creator_application_approved',
                'Creator identity approved',
                'Your creator profile is verified. Your enabled publishing and earnings permissions are shown in the Creator Portal.',
                '/creator',
                ['creator_name' => $application->display_name],
                'creator_verification'
            );
            $this->communications->notify(
                $user,
                "creator-application:{$application->id}:welcome",
                'creator_welcome',
                'Your creator workspace is ready',
                'Upload your first title, complete your public profile, and follow every review from one place.',
                '/creator',
                ['creator_name' => $application->display_name],
                'creator_welcome'
            );

            return $application->refresh();
        });
    }

    public function streamEvidence(User $viewer, CreatorVerificationEvidence $evidence)
    {
        if (! $viewer->isAdmin() && $viewer->id !== $evidence->user_id) {
            abort(403);
        }
        if ($evidence->deleted_at || ! Storage::disk($evidence->disk)->exists($evidence->path)) {
            abort(404);
        }

        $this->audit->record('creator.evidence_accessed', $viewer, $evidence->user, $evidence);

        return Storage::disk($evidence->disk)->download(
            $evidence->path,
            $evidence->original_filename,
            ['Content-Type' => $evidence->mime_type, 'Cache-Control' => 'private, no-store']
        );
    }
}
