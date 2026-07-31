<?php

namespace App\Services;

use App\Events\MoviePublished;
use App\Events\ShowPublished;
use App\Models\CreatorContentOwnershipHistory;
use App\Models\CreatorContentReview;
use App\Models\CreatorContentSubmission;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatorContentWorkflowService
{
    public function __construct(
        private readonly CreatorAccessService $access,
        private readonly CreatorAuditService $audit,
        private readonly CreatorCommunicationService $communications
    ) {}

    public function initialize(Model $content, User $user, string $origin = 'creator'): CreatorContentSubmission
    {
        $application = $user->creatorApplication;
        $owner = $this->ownerFor($content);

        $content->forceFill([
            'submitted_by' => $user->id,
            'creator_application_id' => $application?->id,
            'submission_origin' => $origin,
            'processing_status' => 'not_started',
            'editorial_status' => 'not_submitted',
            'publication_status' => 'draft',
            'monetization_status' => 'disabled',
        ])->save();

        $submission = CreatorContentSubmission::firstOrCreate(
            [
                'user_id' => $user->id,
                'content_type' => $content::class,
                'content_id' => $content->getKey(),
            ],
            [
                'creator_application_id' => $application?->id,
                'submission_source' => 'metadata',
                'processing_status' => 'not_started',
                'editorial_status' => 'not_submitted',
                'publication_status' => 'draft',
                'monetization_status' => 'disabled',
            ]
        );

        CreatorContentOwnershipHistory::create([
            'content_type' => $content::class,
            'content_id' => $content->getKey(),
            'submitting_user_id' => $user->id,
            'owner_type' => $owner['type'],
            'owner_id' => $owner['id'],
            'action' => 'created',
            'actor_id' => $user->id,
            'occurred_at' => now(),
            'metadata' => ['origin' => $origin],
        ]);
        $this->audit->record('creator.content_created', $user, $user, $content);

        return $submission;
    }

    public function submission(Model $content, User $user, string $source): CreatorContentSubmission
    {
        $submission = CreatorContentSubmission::firstOrCreate(
            [
                'user_id' => $user->id,
                'content_type' => $content::class,
                'content_id' => $content->getKey(),
            ],
            [
                'creator_application_id' => $user->creatorApplication?->id,
                'submission_source' => $source,
                'processing_status' => 'uploading',
                'editorial_status' => $content->editorial_status ?? 'not_submitted',
                'publication_status' => $content->publication_status ?? 'draft',
                'monetization_status' => $content->monetization_status ?? 'disabled',
            ]
        );

        if ($submission->wasRecentlyCreated) {
            $this->communications->notify(
                $user,
                "creator-content-submission:{$submission->id}:received",
                'creator_content_uploaded',
                'Media received',
                "{$content->title} has been received and is entering processing.",
                '/creator/processing',
                ['content_title' => $content->title],
                'creator_content'
            );
        }

        return $submission;
    }

    public function markProcessing(
        Model $content,
        CreatorContentSubmission $submission,
        string $status,
        array $attributes = []
    ): void {
        $allowed = ['uploading', 'queued', 'processing', 'processing_failed', 'ready'];
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid processing state.');
        }

        $submission->update(array_merge([
            'processing_status' => $status,
        ], $attributes));
        $content->forceFill(['processing_status' => $status])->save();

        if (in_array($status, ['processing', 'ready', 'processing_failed'], true) && $submission->user) {
            $template = match ($status) {
                'processing' => 'creator_content_processing',
                'ready' => 'creator_content_ready',
                default => 'creator_content_changes_requested',
            };
            $title = match ($status) {
                'processing' => 'Media processing started',
                'ready' => 'Media processing complete',
                default => 'Media processing needs attention',
            };
            $message = $status === 'processing_failed'
                ? (string) ($attributes['failure_reason'] ?? 'Media processing could not be completed. Open the creator workspace for the next step.')
                : ($status === 'ready'
                    ? 'Your processed media is ready for the next publishing step.'
                    : 'NBX is preparing your media for playback.');
            $this->communications->notify(
                $submission->user,
                "creator-content-submission:{$submission->id}:{$status}",
                $template,
                $title,
                $message,
                '/creator/processing',
                ['content_title' => $content->title ?? 'Your title'],
                'creator_content'
            );
        }
    }

    public function submitForReview(Model $content, User $user, bool $publishAfterApproval = false): void
    {
        if (! $this->access->canSubmitDrafts($user)) {
            throw ValidationException::withMessages(['content' => ['Creator submissions are not enabled for this account.']]);
        }
        if (! $this->isComplete($content)) {
            throw ValidationException::withMessages([
                'content' => ['Add the required title, description, artwork and a verified playable source before submitting.'],
            ]);
        }
        if (($content instanceof Movie || $content instanceof TVShow)
            && ! $content->ownership_declaration_accepted_at) {
            throw ValidationException::withMessages([
                'ownership_declaration' => ['Confirm that you own or are authorized to submit this content.'],
            ]);
        }

        DB::transaction(function () use ($content, $user, $publishAfterApproval) {
            $publication = $publishAfterApproval ? 'publish_after_approval' : 'draft';
            $content->forceFill([
                'editorial_status' => 'submitted_for_review',
                'publication_status' => $publication,
                'publish_status' => 'pending_review',
                'submitted_for_review_at' => now(),
                'is_active' => false,
            ])->save();

            CreatorContentSubmission::where('content_type', $content::class)
                ->where('content_id', $content->getKey())
                ->update([
                    'editorial_status' => 'submitted_for_review',
                    'publication_status' => $publication,
                ]);

            $this->audit->record('creator.content_submitted_for_review', $user, $user, $content, [], [
                'publish_after_approval' => $publishAfterApproval,
            ]);
            $actionUrl = $content instanceof Movie
                ? "/creator/movies/{$content->getKey()}"
                : "/creator/tv-shows/{$content->getKey()}";
            $this->communications->notify(
                $user,
                'creator-content:'.class_basename($content).":{$content->getKey()}:submitted:".$content->updated_at?->timestamp,
                'creator_content_submitted',
                'Content submitted for review',
                "{$content->title} is now awaiting editorial review.",
                $actionUrl,
                ['content_title' => $content->title],
                'creator_content'
            );
        });
    }

    public function moderate(
        Model $content,
        User $admin,
        string $decision,
        ?string $creatorMessage = null,
        ?string $internalNotes = null,
        array $requestedChanges = []
    ): void {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['moderation' => ['Only administrators may moderate creator content.']]);
        }
        if (! in_array($decision, ['approved', 'changes_requested', 'rejected', 'suspended'], true)) {
            throw ValidationException::withMessages(['decision' => ['Invalid moderation decision.']]);
        }

        DB::transaction(function () use ($content, $admin, $decision, $creatorMessage, $internalNotes, $requestedChanges) {
            $updates = [
                'editorial_status' => $decision,
                'moderation_notes' => $creatorMessage,
                'approved_by' => $decision === 'approved' ? $admin->id : null,
                'approved_at' => $decision === 'approved' ? now() : null,
                'publish_status' => $decision === 'approved' ? 'approved' : $decision,
            ];

            if ($decision === 'approved' && $content->publication_status === 'publish_after_approval') {
                $updates = array_merge($updates, [
                    'publication_status' => 'published',
                    'publish_status' => 'published',
                    'published_by' => $admin->id,
                    'published_at' => now(),
                    'is_active' => true,
                ]);
            } else {
                $updates['is_active'] = false;
            }
            $content->forceFill($updates)->save();

            CreatorContentReview::create([
                'content_type' => $content::class,
                'content_id' => $content->getKey(),
                'status' => $decision,
                'creator_message' => $creatorMessage,
                'internal_notes' => $internalNotes,
                'requested_changes' => $requestedChanges ?: null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            CreatorContentSubmission::where('content_type', $content::class)
                ->where('content_id', $content->getKey())
                ->update([
                    'editorial_status' => $decision,
                    'publication_status' => $content->publication_status,
                ]);

            if ($content->is_active) {
                if ($content instanceof Movie) {
                    event(new MoviePublished($content->fresh()));
                } elseif ($content instanceof TVShow) {
                    event(new ShowPublished($content->fresh()));
                }
            }
            $this->audit->record('creator.content_'.$decision, $admin, $content->submittingUser, $content);
            if ($content->submitted_by) {
                $messages = [
                    'approved' => 'Your submission has been approved.',
                    'changes_requested' => $creatorMessage ?: 'Changes are required before this submission can be approved.',
                    'rejected' => $creatorMessage ?: 'This submission was not approved.',
                    'suspended' => $creatorMessage ?: 'This title has been suspended.',
                ];
                $creator = $content->submittingUser;
                if ($creator) {
                    $actionUrl = $content instanceof Movie
                        ? "/creator/movies/{$content->getKey()}"
                        : ($content instanceof TVShow
                            ? "/creator/tv-shows/{$content->getKey()}"
                            : '/creator/processing');
                    $template = match (true) {
                        $decision === 'approved' && $content->is_active => 'creator_content_published',
                        $decision === 'approved' => 'creator_content_approved',
                        $decision === 'changes_requested' => 'creator_content_changes_requested',
                        $decision === 'rejected' => 'creator_content_rejected',
                        default => 'creator_content_unpublished',
                    };
                    $this->communications->notify(
                        $creator,
                        'creator-content:'.class_basename($content).":{$content->getKey()}:{$decision}:".$content->updated_at?->timestamp,
                        $template,
                        'Content '.str_replace('_', ' ', $decision),
                        "{$content->title}: {$messages[$decision]}",
                        $actionUrl,
                        [
                            'content_title' => $content->title,
                            'message' => $messages[$decision],
                        ],
                        'creator_content'
                    );
                }
            }
        });
    }

    public function reassignOwner(
        Model $content,
        User $admin,
        string $ownerType,
        ?int $ownerId,
        string $reason
    ): void {
        if (! $admin->isAdmin() || ! in_array($ownerType, ['vj', 'media_library', 'platform'], true)) {
            throw ValidationException::withMessages(['owner' => ['Only administrators may reassign creator ownership.']]);
        }
        $previous = $this->ownerFor($content);
        $new = match ($ownerType) {
            'vj' => ['type' => \App\Models\VJ::class, 'id' => \App\Models\VJ::findOrFail($ownerId)->id],
            'media_library' => ['type' => \App\Models\MediaLibrary::class, 'id' => \App\Models\MediaLibrary::findOrFail($ownerId)->id],
            default => ['type' => null, 'id' => null],
        };

        DB::transaction(function () use ($content, $admin, $ownerType, $new, $previous, $reason): void {
            $content->forceFill([
                'vj_id' => $ownerType === 'vj' ? $new['id'] : null,
                'media_library_id' => $ownerType === 'media_library' ? $new['id'] : null,
            ])->save();
            CreatorContentOwnershipHistory::create([
                'content_type' => $content::class,
                'content_id' => $content->getKey(),
                'submitting_user_id' => $content->submitted_by,
                'owner_type' => $new['type'],
                'owner_id' => $new['id'],
                'previous_owner_type' => $previous['type'],
                'previous_owner_id' => $previous['id'],
                'action' => 'reassigned',
                'reason' => $reason,
                'actor_id' => $admin->id,
                'occurred_at' => now(),
                'metadata' => ['historical_earnings_transferred' => false],
            ]);
            $this->audit->record('creator.content_owner_reassigned', $admin, $content->submittingUser, $content, $previous, $new);
        });
    }

    private function isComplete(Model $content): bool
    {
        if (! filled($content->title) || ! filled($content->description) || ! filled($content->thumbnail)) {
            return false;
        }

        if ($content instanceof TVShow) {
            return $content->seasons()
                ->whereHas('episodes.videoSources', fn ($query) => $query->where('is_active', true))
                ->exists();
        }

        if ($content instanceof Movie || $content instanceof Episode) {
            return $content->videoSources()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->where('health_status', 'healthy')
                        ->orWhereNull('health_status');
                })
                ->exists();
        }

        return false;
    }

    private function ownerFor(Model $content): array
    {
        if ($content->getAttribute('media_library_id')) {
            return ['type' => \App\Models\MediaLibrary::class, 'id' => $content->getAttribute('media_library_id')];
        }
        if ($content->getAttribute('vj_id')) {
            return ['type' => \App\Models\VJ::class, 'id' => $content->getAttribute('vj_id')];
        }

        return ['type' => null, 'id' => null];
    }
}
