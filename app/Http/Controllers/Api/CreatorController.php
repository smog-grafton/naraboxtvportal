<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreatorApplication;
use App\Models\CreatorAuditLog;
use App\Models\CreatorClaim;
use App\Models\CreatorInformationRequest;
use App\Models\CreatorInformationResponse;
use App\Models\CreatorVerificationEvidence;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Models\VJ;
use App\Services\CreatorIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreatorController extends Controller
{
    public function __construct(private readonly CreatorIdentityService $identity)
    {
    }

    public function getApplication(Request $request): JsonResponse
    {
        $application = $request->user()->creatorApplication()
            ->with([
                'claims.claimable',
                'evidence',
                'informationRequests.responses.evidence',
                'permission',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $application ? $this->formatApplication($application) : null,
            'message' => $application ? null : 'No application found.',
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'creator_types' => [
                    ['value' => 'vj', 'label' => 'VJ / translator'],
                    ['value' => 'media_library', 'label' => 'Media library / studio'],
                ],
                'application_modes' => [
                    ['value' => 'create', 'label' => 'Create a new profile'],
                    ['value' => 'claim', 'label' => 'Claim an existing profile'],
                ],
                'claimable_profiles' => [
                    'vjs' => VJ::query()->select('id', 'name', 'slug', 'image', 'is_verified')->orderBy('name')->get(),
                    'media_libraries' => MediaLibrary::query()->select('id', 'name', 'slug', 'image', 'is_verified')->orderBy('name')->get(),
                ],
                'evidence_methods' => [
                    ['value' => 'private_video', 'label' => 'Private verification video'],
                    ['value' => 'official_channel', 'label' => 'Official channel challenge'],
                    ['value' => 'known_contact', 'label' => 'Known-contact verification'],
                ],
                'evidence_notice' => 'Verification material is private, available only to you and authorized reviewers, and removed under the configured retention policy.',
            ],
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $this->validateApplication($request);
        $application = $this->identity->saveDraft($request->user(), $validated);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('creator-profiles', 'public');
            $application->update(['profile_image' => $path]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Creator application draft saved. Complete ownership evidence and submit it when ready.',
            'data' => $this->formatApplication($application->fresh()),
        ], $application->wasRecentlyCreated ? 201 : 200);
    }

    public function updateApplication(Request $request): JsonResponse
    {
        $response = $this->apply($request);
        $response->setStatusCode(200);

        return $response;
    }

    public function submitApplication(Request $request): JsonResponse
    {
        $application = $request->user()->creatorApplication;
        abort_unless($application, 404, 'Creator application not found.');
        $application = $this->identity->submit($request->user(), $application);

        return response()->json([
            'success' => true,
            'message' => 'Your creator application has been submitted for administrator review.',
            'data' => $this->formatApplication($application),
        ]);
    }

    public function startClaim(Request $request): JsonResponse
    {
        $application = $request->user()->creatorApplication;
        abort_unless($application, 422, 'Save the creator application before starting a claim.');

        $validated = $request->validate([
            'claimable_type' => ['required', Rule::in(['vj', 'media_library'])],
            'claimable_id' => ['required', 'integer', 'min:1'],
            'legal_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'official_social_url' => [
                'nullable',
                'required_without_all:telegram_url,youtube_url,facebook_url,tiktok_url,existing_business_contact,sample_work_url',
                'url',
                'max:2048',
            ],
            'telegram_url' => ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'tiktok_url' => ['nullable', 'url', 'max:2048'],
            'existing_business_contact' => ['nullable', 'string', 'max:2000'],
            'sample_work_url' => ['nullable', 'url', 'max:2048'],
            'relationship_explanation' => ['required', 'string', 'min:30', 'max:5000'],
            'verification_method' => ['required', Rule::in(['private_video', 'official_channel', 'known_contact'])],
        ]);

        $claim = $this->identity->startClaim($request->user(), $application, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Claim draft created. Follow the private verification instructions before submitting.',
            'data' => $this->formatClaim($claim),
        ], 201);
    }

    /**
     * Backward-compatible route with the secure evidence contract.
     */
    public function claimVj(Request $request): JsonResponse
    {
        $request->merge([
            'claimable_type' => 'vj',
            'claimable_id' => $request->input('claimable_id', $request->input('vj_id')),
        ]);

        return $this->startClaim($request);
    }

    public function getClaimStatus(Request $request): JsonResponse
    {
        $claims = CreatorClaim::where('user_id', $request->user()->id)
            ->with('claimable')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $claims->map(fn (CreatorClaim $claim) => $this->formatClaim($claim)),
        ]);
    }

    public function uploadEvidence(Request $request): JsonResponse
    {
        $application = $request->user()->creatorApplication;
        abort_unless($application, 404, 'Creator application not found.');
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['verification_video', 'supporting_image', 'supporting_document', 'channel_challenge'])],
            'claim_id' => ['nullable', 'integer'],
            'file' => [
                'required',
                'file',
                'max:153600',
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-matroska,image/jpeg,image/png,image/webp,application/pdf',
            ],
        ]);
        $claim = isset($validated['claim_id'])
            ? CreatorClaim::where('user_id', $request->user()->id)->findOrFail($validated['claim_id'])
            : null;
        if ($validated['kind'] === 'verification_video'
            && ! str_starts_with((string) $request->file('file')->getMimeType(), 'video/')) {
            return response()->json(['success' => false, 'message' => 'Verification evidence must be a video file.'], 422);
        }

        $evidence = $this->identity->storeEvidence(
            $request->user(),
            $application,
            $request->file('file'),
            $validated['kind'],
            $claim
        );

        return response()->json([
            'success' => true,
            'message' => 'Private evidence uploaded.',
            'data' => $this->formatEvidence($evidence),
        ], 201);
    }

    public function downloadEvidence(Request $request, CreatorVerificationEvidence $evidence)
    {
        return $this->identity->streamEvidence($request->user(), $evidence);
    }

    public function respondToInformationRequest(Request $request, CreatorInformationRequest $informationRequest): JsonResponse
    {
        $application = $request->user()->creatorApplication;
        abort_unless(
            $application
            && $informationRequest->user_id === $request->user()->id
            && $informationRequest->creator_application_id === $application->id
            && in_array($informationRequest->status, ['open', 'needs_changes'], true),
            404
        );

        $rules = $this->responseRules($informationRequest, false);
        $validated = $request->validate($rules);
        $evidence = null;
        if ($request->hasFile('file')) {
            $claim = $informationRequest->creator_claim_id
                ? CreatorClaim::where('user_id', $request->user()->id)->find($informationRequest->creator_claim_id)
                : null;
            $evidence = $this->identity->storeEvidence(
                $request->user(),
                $application,
                $request->file('file'),
                "information_request_{$informationRequest->id}",
                $claim
            );
        }

        $response = $informationRequest->responses()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'needs_changes'])
            ->latest()
            ->first() ?? new CreatorInformationResponse([
                'information_request_id' => $informationRequest->id,
                'user_id' => $request->user()->id,
            ]);
        $response->fill([
            'response_text' => array_key_exists('value', $validated)
                ? $validated['value']
                : $response->response_text,
            'evidence_id' => $evidence?->id ?? $response->evidence_id,
            'status' => 'draft',
            'submitted_at' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Response saved. Submit all requested information when you are ready.',
            'data' => [
                'id' => $response->id,
                'status' => $response->status,
                'submitted_at' => $response->submitted_at?->toIso8601String(),
            ],
        ], $response->wasRecentlyCreated ? 201 : 200);
    }

    public function submitInformationResponses(Request $request): JsonResponse
    {
        $application = $request->user()->creatorApplication()
            ->with(['informationRequests.responses.evidence'])
            ->first();
        abort_unless($application, 404, 'Creator application not found.');
        if (! in_array($application->status, [
            CreatorApplication::STATUS_INFORMATION_REQUIRED,
            'needs_changes',
        ], true)) {
            throw ValidationException::withMessages([
                'application' => ['There is no additional-information review waiting for submission.'],
            ]);
        }

        $requests = $application->informationRequests
            ->whereIn('status', ['open', 'needs_changes']);
        if ($requests->isEmpty()) {
            throw ValidationException::withMessages([
                'additional_information' => ['There are no open information requests.'],
            ]);
        }

        $missing = $requests
            ->filter(fn (CreatorInformationRequest $item) => $item->is_required)
            ->filter(function (CreatorInformationRequest $item): bool {
                $response = $item->responses
                    ->whereIn('status', ['draft', 'submitted'])
                    ->sortByDesc('id')
                    ->first();

                return ! $response || (blank($response->response_text) && ! $response->evidence_id);
            })
            ->pluck('field_label')
            ->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'additional_information' => [
                    'Complete these requested items first: '.$missing->join(', ').'.',
                ],
            ]);
        }

        DB::transaction(function () use ($application, $requests, $request): void {
            $responseIds = [];
            foreach ($requests as $informationRequest) {
                $response = $informationRequest->responses()
                    ->where('user_id', $request->user()->id)
                    ->whereIn('status', ['draft', 'submitted'])
                    ->latest()
                    ->first();
                if ($response) {
                    $response->update([
                        'status' => 'submitted',
                        'submitted_at' => $response->submitted_at ?: now(),
                    ]);
                    $responseIds[] = $response->id;
                }
                $informationRequest->update(['status' => 'submitted']);
            }

            $previousStatus = $application->status;
            $application->update([
                'status' => CreatorApplication::STATUS_UNDER_REVIEW,
                'resubmitted_at' => now(),
                'rejection_reason' => null,
            ]);
            app(\App\Services\CreatorAuditService::class)->record(
                'creator.additional_information_submitted',
                $request->user(),
                $request->user(),
                $application,
                ['status' => $previousStatus],
                ['status' => CreatorApplication::STATUS_UNDER_REVIEW],
                [
                    'request_ids' => $requests->pluck('id')->values()->all(),
                    'response_ids' => $responseIds,
                ]
            );
        });

        $application->refresh();
        $creatorActionUrl = '/creator/application';
        app(\App\Services\CreatorCommunicationService::class)->notify(
            $request->user(),
            "creator-application:{$application->id}:information-submitted:{$application->resubmitted_at?->timestamp}",
            'creator_application_information_submitted',
            'Information submitted',
            'Your new information has been sent to the NaraBox review team.',
            $creatorActionUrl,
            [
                'application_id' => $application->id,
                'action_label' => 'View application',
                'creator_name' => $application->display_name,
            ],
            'creator_application_under_review'
        );

        User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->each(function (User $admin) use ($application): void {
                app(\App\Services\CreatorCommunicationService::class)->notify(
                    $admin,
                    "creator-application:{$application->id}:information-ready:{$application->resubmitted_at?->timestamp}:admin:{$admin->id}",
                    'creator_application_information_submitted',
                    'Creator information ready for review',
                    "{$application->display_name} submitted the requested information.",
                    rtrim((string) config('app.url'), "/")."/admin/creator-applications/{$application->id}",
                    [
                        'application_id' => $application->id,
                        'action_label' => 'Review application',
                        'creator_name' => $application->display_name,
                    ],
                    'creator_application_review_ready'
                );
            });

        return response()->json([
            'success' => true,
            'message' => 'Information submitted. Your application is back with the review team.',
            'data' => $this->formatApplication($application),
        ]);
    }

    private function validateApplication(Request $request): array
    {
        return $request->validate([
            'creator_type' => ['required', Rule::in(['vj', 'media_library'])],
            'application_mode' => ['nullable', Rule::in(['create', 'claim'])],
            'onboarding_step' => ['nullable', 'integer', 'between:1,6'],
            'display_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'public_email' => ['nullable', 'email:rfc', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'social_links' => ['nullable', 'array', 'max:10'],
            'social_links.*' => ['nullable', 'url', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function responseRules(CreatorInformationRequest $informationRequest, bool $requireComplete = true): array
    {
        $required = $requireComplete && $informationRequest->is_required ? 'required' : 'nullable';
        $type = $informationRequest->field_type;
        if (in_array($type, ['image', 'document', 'file', 'video'], true)) {
            $mimes = implode(',', $informationRequest->allowed_mime_types ?: [
                'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
                'video/mp4', 'video/quicktime', 'video/webm',
            ]);

            return ['file' => [$required, 'file', 'max:'.($informationRequest->max_file_size_kb ?: 102400), 'mimetypes:'.$mimes]];
        }

        $rule = match ($type) {
            'number' => 'numeric',
            'email' => 'email:rfc',
            'url' => 'url',
            'date' => 'date',
            'phone' => 'string',
            default => 'string',
        };

        return ['value' => [$required, $rule, 'max:10000']];
    }

    private function formatApplication(CreatorApplication $application): array
    {
        $application->loadMissing(['claims.claimable', 'evidence', 'informationRequests.responses.evidence', 'permission']);

        return [
            'id' => $application->id,
            'creator_type' => $application->creator_type,
            'application_mode' => $application->application_mode,
            'onboarding_step' => max(1, min(6, (int) ($application->onboarding_step ?: 1))),
            'display_name' => $application->display_name,
            'legal_name' => $application->legal_name,
            'phone_number' => $application->phone_number,
            'public_email' => $application->public_email,
            'website_url' => $application->website_url,
            'social_links' => $application->social_links ?? [],
            'bio' => $application->bio,
            'profile_image' => $application->profile_image ? Storage::disk('public')->url($application->profile_image) : null,
            'genres' => $application->genres ?? [],
            'status' => $application->status,
            'status_message' => $this->statusMessage($application),
            'current_review_stage' => match ($application->status) {
                CreatorApplication::STATUS_DRAFT => 'Application setup',
                CreatorApplication::STATUS_SUBMITTED, 'pending' => 'Waiting for review',
                CreatorApplication::STATUS_UNDER_REVIEW => 'Review in progress',
                CreatorApplication::STATUS_INFORMATION_REQUIRED, 'needs_changes' => 'Creator response needed',
                CreatorApplication::STATUS_APPROVED => 'Approved',
                CreatorApplication::STATUS_REJECTED => 'Decision complete',
                CreatorApplication::STATUS_SUSPENDED, CreatorApplication::STATUS_REVOKED => 'Account review',
                default => 'Application review',
            },
            'verification_status' => $application->verification_status,
            'challenge_phrase' => $application->challenge_phrase,
            'challenge_expires_at' => $application->challenge_expires_at?->toIso8601String(),
            'rejection_reason' => $application->rejection_reason,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'resubmitted_at' => $application->resubmitted_at?->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'updated_at' => $application->updated_at?->toIso8601String(),
            'permissions' => $application->permission ? [
                'identity_verified' => $application->permission->identity_verified,
                'draft_submission_enabled' => $application->permission->draft_submission_enabled,
                'publishing_enabled' => $application->permission->publishing_enabled,
                'monetization_enabled' => $application->permission->monetization_enabled,
                'withdrawals_enabled' => $application->permission->withdrawals_enabled,
                'is_suspended' => $application->permission->is_suspended,
            ] : null,
            'claims' => $application->claims->map(fn (CreatorClaim $claim) => $this->formatClaim($claim)),
            'evidence' => $application->evidence->map(fn (CreatorVerificationEvidence $evidence) => $this->formatEvidence($evidence)),
            'information_requests' => $application->informationRequests->map(fn (CreatorInformationRequest $item) => [
                'id' => $item->id,
                'field_label' => $item->field_label,
                'instructions' => $item->instructions,
                'field_type' => $item->field_type,
                'step_key' => $item->step_key ?: 'verification',
                'is_required' => $item->is_required,
                'options' => $item->options,
                'allowed_mime_types' => $item->allowed_mime_types,
                'max_file_size_kb' => $item->max_file_size_kb,
                'due_at' => $item->due_at?->toIso8601String(),
                'creator_message' => $item->creator_message,
                'status' => $item->status,
                'responses' => $item->responses->map(fn ($response) => [
                    'id' => $response->id,
                    'status' => $response->status,
                    'response_text' => $response->response_text,
                    'evidence_id' => $response->evidence_id,
                    'evidence_name' => $response->evidence?->original_filename,
                    'review_feedback' => $response->review_feedback,
                    'submitted_at' => $response->submitted_at?->toIso8601String(),
                ]),
            ]),
            'request_progress' => $this->requestProgress($application),
            'allowed_actions' => [
                'save_response' => in_array($application->status, [
                    CreatorApplication::STATUS_INFORMATION_REQUIRED,
                    'needs_changes',
                ], true),
                'submit_responses' => in_array($application->status, [
                    CreatorApplication::STATUS_INFORMATION_REQUIRED,
                    'needs_changes',
                ], true),
                'edit_application' => $application->canBeEdited(),
                'open_creator_dashboard' => (bool) $application->permission?->identity_verified,
            ],
            'status_history' => CreatorAuditLog::query()
                ->where('auditable_type', CreatorApplication::class)
                ->where('auditable_id', (string) $application->id)
                ->whereIn('event', [
                    'creator.application_draft_saved',
                    'creator.application_submitted',
                    'creator.application_review_started',
                    'creator.additional_information_requested',
                    'creator.additional_information_submitted',
                    'creator.application_resubmitted',
                    'creator.identity_approved',
                ])
                ->oldest()
                ->get(['id', 'event', 'created_at'])
                ->map(fn (CreatorAuditLog $entry) => [
                    'id' => $entry->id,
                    'label' => match ($entry->event) {
                        'creator.application_draft_saved' => 'Application saved',
                        'creator.application_submitted' => 'Application submitted',
                        'creator.application_review_started' => 'Review started',
                        'creator.additional_information_requested' => 'More information requested',
                        'creator.additional_information_submitted', 'creator.application_resubmitted' => 'Information submitted',
                        'creator.identity_approved' => 'Creator account approved',
                        default => 'Application updated',
                    },
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }

    private function requestProgress(CreatorApplication $application): array
    {
        $active = $application->informationRequests
            ->whereIn('status', ['open', 'needs_changes', 'submitted']);
        $completed = $active->filter(function (CreatorInformationRequest $item): bool {
            $response = $item->responses->sortByDesc('id')->first();

            return $response
                && in_array($response->status, ['draft', 'submitted', 'accepted'], true)
                && (filled($response->response_text) || $response->evidence_id);
        })->count();

        return [
            'completed' => $completed,
            'total' => $active->count(),
            'ready_to_submit' => $active->where('is_required', true)->every(function (CreatorInformationRequest $item): bool {
                $response = $item->responses->sortByDesc('id')->first();

                return $response
                    && in_array($response->status, ['draft', 'submitted', 'accepted'], true)
                    && (filled($response->response_text) || $response->evidence_id);
            }),
        ];
    }

    private function statusMessage(CreatorApplication $application): string
    {
        return match ($application->status) {
            CreatorApplication::STATUS_DRAFT => 'Your progress is saved. Continue when you are ready.',
            CreatorApplication::STATUS_SUBMITTED, 'pending' => 'Your application has been received and is waiting for review.',
            CreatorApplication::STATUS_UNDER_REVIEW => 'The NaraBox team is reviewing your creator information.',
            CreatorApplication::STATUS_INFORMATION_REQUIRED, 'needs_changes' => 'Please provide the requested information so the review can continue.',
            CreatorApplication::STATUS_APPROVED => 'Your creator profile is ready.',
            CreatorApplication::STATUS_REJECTED => $application->rejection_reason ?: 'Your application was not approved.',
            CreatorApplication::STATUS_SUSPENDED => $application->suspension_reason ?: 'Your creator account is temporarily paused.',
            CreatorApplication::STATUS_REVOKED => 'Your creator access is no longer active.',
            default => 'Your creator application is being processed.',
        };
    }

    private function formatClaim(CreatorClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'claimable_type' => $claim->claimable_type === VJ::class ? 'vj' : 'media_library',
            'claimable_id' => $claim->claimable_id,
            'creator_name' => $claim->creator_name,
            'profile_slug' => $claim->claimable?->slug,
            'status' => $claim->status,
            'verification_method' => $claim->verification_method,
            'challenge_phrase' => $claim->challenge_phrase,
            'challenge_expires_at' => $claim->challenge_expires_at?->toIso8601String(),
            'rejection_reason' => $claim->rejection_reason,
            'submitted_at' => $claim->submitted_at?->toIso8601String(),
            'reviewed_at' => $claim->reviewed_at?->toIso8601String(),
        ];
    }

    private function formatEvidence(CreatorVerificationEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'kind' => $evidence->kind,
            'original_filename' => $evidence->original_filename,
            'mime_type' => $evidence->mime_type,
            'size_bytes' => $evidence->size_bytes,
            'status' => $evidence->status,
            'uploaded_at' => $evidence->uploaded_at?->toIso8601String(),
            'download_endpoint' => route('creator.evidence.download', ['evidence' => $evidence->id], false),
        ];
    }
}
