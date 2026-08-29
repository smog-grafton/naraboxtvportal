<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\PartnerBenefitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerBenefitController extends Controller
{
    public function claimFirstMovie(Request $request, PartnerBenefitService $benefits): JsonResponse
    {
        $validated = $request->validate([
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
        ]);
        $movie = Movie::query()->where('is_active', true)->findOrFail($validated['movie_id']);
        $claim = $benefits->claimFirstMovie($request->user(), $movie);

        return response()->json(['success' => true, 'data' => [
            'claim_id' => $claim->id,
            'movie_id' => $claim->movie_id,
            'status' => $claim->status,
            'message' => $claim->partner->display_name.' has offered you this movie for FREE.',
        ]]);
    }
}
