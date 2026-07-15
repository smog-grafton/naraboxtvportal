<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreatorApplication;
use App\Models\Genre;
use App\Models\MediaLibrary;
use App\Models\Role;
use App\Models\VJ;
use App\Models\VJClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CreatorController extends Controller
{
    /**
     * Get the current user's creator application status.
     */
    public function getApplication(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $user->creatorApplication;

        if (!$application) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No application found.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatApplication($application),
        ]);
    }

    /**
     * Submit a new creator application.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        // Prevent duplicate applications
        if ($user->creatorApplication) {
            $existing = $user->creatorApplication;

            if ($existing->isApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your creator account has already been approved.',
                ], 422);
            }

            if ($existing->isPending() || $existing->isUnderReview()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending application under review.',
                    'data' => $this->formatApplication($existing),
                ], 422);
            }

            if ($existing->isRejected()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your application was rejected. Please contact support for more information.',
                    'data' => $this->formatApplication($existing),
                ], 422);
            }
        }

        $validated = $request->validate([
            'creator_type'  => ['required', Rule::in(['vj', 'media_library'])],
            'display_name'  => ['required', 'string', 'max:150'],
            'bio'           => ['nullable', 'string', 'max:2000'],
            'genres'        => ['nullable', 'array'],
            'genres.*'      => ['integer', 'exists:genres,id'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('creator-applications', 'public');
        }

        $application = CreatorApplication::create([
            'user_id'       => $user->id,
            'creator_type'  => $validated['creator_type'],
            'display_name'  => $validated['display_name'],
            'bio'           => $validated['bio'] ?? null,
            'profile_image' => $profileImagePath,
            'genres'        => $validated['genres'] ?? null,
            'status'        => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your creator application has been submitted successfully. We will review it and notify you.',
            'data' => $this->formatApplication($application),
        ], 201);
    }

    /**
     * Update an existing application (only when status is needs_changes).
     */
    public function updateApplication(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $user->creatorApplication;

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'No application found.',
            ], 404);
        }

        if (!$application->canBeEdited()) {
            return response()->json([
                'success' => false,
                'message' => 'Your application cannot be edited at this stage (status: ' . $application->status . ').',
            ], 422);
        }

        $validated = $request->validate([
            'display_name'  => ['required', 'string', 'max:150'],
            'bio'           => ['nullable', 'string', 'max:2000'],
            'genres'        => ['nullable', 'array'],
            'genres.*'      => ['integer', 'exists:genres,id'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $profileImagePath = $application->profile_image;
        if ($request->hasFile('profile_image')) {
            if ($profileImagePath) {
                Storage::disk('public')->delete($profileImagePath);
            }
            $profileImagePath = $request->file('profile_image')->store('creator-applications', 'public');
        }

        $application->update([
            'display_name'  => $validated['display_name'],
            'bio'           => $validated['bio'] ?? $application->bio,
            'profile_image' => $profileImagePath,
            'genres'        => $validated['genres'] ?? $application->genres,
            'status'        => 'pending',
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your application has been resubmitted for review.',
            'data' => $this->formatApplication($application->fresh()),
        ]);
    }

    /**
     * Claim an existing VJ profile (for users who are the actual VJ).
     */
    public function claimVj(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'vj_id' => ['required', 'integer', 'exists:vjs,id'],
        ]);

        $vjId = $validated['vj_id'];
        $vj = VJ::find($vjId);

        if ($vj->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'This VJ profile is already claimed by another user.',
            ], 422);
        }

        $existing = VJClaimRequest::where('user_id', $user->id)
            ->where('vj_id', $vjId)
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending claim request for this VJ.',
                    'data' => $this->formatClaimRequest($existing),
                ], 422);
            }
            if ($existing->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already been approved for this VJ profile.',
                ], 422);
            }
        }

        $claim = VJClaimRequest::updateOrCreate(
            ['user_id' => $user->id, 'vj_id' => $vjId],
            ['status' => 'pending', 'rejection_reason' => null]
        );

        return response()->json([
            'success' => true,
            'message' => 'Your VJ claim request has been submitted. An admin will review it shortly.',
            'data' => $this->formatClaimRequest($claim),
        ], 201);
    }

    /**
     * Get current user's VJ claim status.
     */
    public function getClaimStatus(Request $request): JsonResponse
    {
        $claims = VJClaimRequest::where('user_id', $request->user()->id)
            ->with('vj')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $claims->map(fn ($c) => $this->formatClaimRequest($c)),
        ]);
    }

    private function formatClaimRequest(VJClaimRequest $claim): array
    {
        return [
            'id' => $claim->id,
            'vj_id' => $claim->vj_id,
            'vj_name' => $claim->vj?->name,
            'status' => $claim->status,
            'rejection_reason' => $claim->rejection_reason,
            'created_at' => $claim->created_at?->toIso8601String(),
            'reviewed_at' => $claim->reviewed_at?->toIso8601String(),
        ];
    }

    private function formatApplication(CreatorApplication $application): array
    {
        return [
            'id'               => $application->id,
            'creator_type'     => $application->creator_type,
            'display_name'     => $application->display_name,
            'bio'              => $application->bio,
            'profile_image'    => $application->profile_image
                ? asset('storage/' . $application->profile_image)
                : null,
            'genres'           => $application->genres,
            'status'           => $application->status,
            'rejection_reason' => $application->rejection_reason,
            'submitted_at'     => $application->created_at?->toIso8601String(),
            'reviewed_at'      => $application->reviewed_at?->toIso8601String(),
        ];
    }
}
