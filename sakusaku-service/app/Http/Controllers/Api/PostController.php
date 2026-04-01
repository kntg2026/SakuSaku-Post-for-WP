<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitPostRequest;
use App\Http\Resources\PostResource;
use App\Jobs\ProcessDocSubmission;
use App\Models\Post;
use App\Services\TenantContext;
use App\Services\WpBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Post::where('tenant_id', $user->tenant_id)
            ->with(['category', 'user'])
            ->withCount('images');

        // Posters see only their own posts; admins see all
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return PostResource::collection(
            $query->orderByDesc('created_at')->paginate(20)
        );
    }

    public function store(SubmitPostRequest $request, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user();
        $docId = $request->docId();

        if (!$docId) {
            return response()->json(['error' => 'Invalid Google Docs URL'], 422);
        }

        $post = Post::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'google_doc_id' => $docId,
            'google_doc_url' => $request->input('google_doc_url'),
            'category_id' => $request->input('category_id'),
            'poster_comment' => $request->input('poster_comment'),
            'status' => PostStatus::Pending,
        ]);

        ProcessDocSubmission::dispatch($post);

        return response()->json(new PostResource($post->load('category', 'user')), 202);
    }

    public function show(Request $request, Post $post): PostResource
    {
        $user = $request->user();

        if ($post->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if (!$user->isAdmin() && $post->user_id !== $user->id) {
            abort(403);
        }

        return new PostResource($post->load(['category', 'user', 'tags'])->loadCount('images'));
    }

    public function destroy(Request $request, Post $post, WpBridgeService $wpBridge): JsonResponse
    {
        $user = $request->user();

        if ($post->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if (!$user->isAdmin() && $post->user_id !== $user->id) {
            abort(403);
        }

        if (in_array($post->status, [PostStatus::Published])) {
            return response()->json(['error' => 'Cannot delete published posts'], 422);
        }

        // Delete WP draft if exists
        if ($post->wp_post_id) {
            try {
                $wpBridge->deletePost($post->wp_post_id);
            } catch (\Throwable $e) {
                // Log but don't block deletion
            }
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted']);
    }

    public function publish(Request $request, Post $post, WpBridgeService $wpBridge): JsonResponse
    {
        $user = $request->user();

        if ($post->tenant_id !== $user->tenant_id) {
            abort(404);
        }

        if (!$user->hasMinLevel(2)) {
            return response()->json(['error' => 'Level 2 or higher required'], 403);
        }

        if (!in_array($post->status, [PostStatus::Draft, PostStatus::Approved])) {
            return response()->json(['error' => 'Post must be in draft or approved status'], 422);
        }

        if (!$post->wp_post_id) {
            return response()->json(['error' => 'WP post not created yet'], 422);
        }

        $result = $wpBridge->publishPost($post->wp_post_id);

        $post->update([
            'status' => PostStatus::Published,
            'published_at' => now(),
            'published_by' => $user->id,
            'wp_permalink' => $result['permalink'] ?? null,
        ]);

        return response()->json(new PostResource($post->fresh(['category', 'user'])));
    }
}
