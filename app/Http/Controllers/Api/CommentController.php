<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get comments for a specific media item
     */
    public function index(Request $request, $mediaId)
    {
        $comments = Comment::where('media_id', $mediaId)
            ->whereNull('parent_id') // Only top-level comments
            ->with(['replies' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $comments->map(fn($comment) => $this->formatComment($comment)),
        ]);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|exists:movies,id',
            'text' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        
        // If user is not authenticated, allow anonymous comments with user_name
        if (!$user) {
            $validator = Validator::make($request->all(), [
                'user_name' => 'required|string|max:255',
                'avatar' => 'nullable|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        $comment = Comment::create([
            'media_id' => $request->media_id,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->name : $request->user_name,
            'avatar' => $user ? ($user->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($user->name)) : ($request->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode($request->user_name)),
            'text' => $request->text,
            'parent_id' => $request->parent_id,
            'likes' => 0,
        ]);

        // Load relationships
        $comment->load(['replies']);

        return response()->json([
            'success' => true,
            'data' => $this->formatComment($comment),
        ], 201);
    }

    /**
     * Toggle like on a comment
     */
    public function toggleLike(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        
        // For now, we'll just increment likes
        // In a production app, you'd want to track which users liked which comments
        $comment->increment('likes');

        return response()->json([
            'success' => true,
            'data' => $this->formatComment($comment->fresh()),
        ]);
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $user = $request->user();

        // Only allow deletion if user owns the comment or is admin
        if (!$user || ($comment->user_id && $comment->user_id !== $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete replies first
        Comment::where('parent_id', $comment->id)->delete();
        
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully',
        ]);
    }

    /**
     * Format comment for API response
     */
    private function formatComment(Comment $comment): array
    {
        return [
            'id' => (string) $comment->id,
            'user' => $comment->user_name,
            'avatar' => $comment->avatar,
            'text' => $comment->text,
            'likes' => $comment->likes,
            'isLiked' => false, // TODO: Implement user-specific like tracking
            'date' => $comment->created_at->format('M d, Y'),
            'replies' => $comment->replies->map(fn($reply) => $this->formatComment($reply)),
        ];
    }
}

