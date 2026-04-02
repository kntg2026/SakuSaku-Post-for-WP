<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\TenantContext;
use App\Services\WpBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostManagementController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): AnonymousResourceCollection
    {
        $query = Post::where('tenant_id', $tenantContext->id())
            ->with(['category', 'user'])
            ->withCount('images');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        return PostResource::collection(
            $query->orderByDesc('created_at')->paginate(20)
        );
    }

    public function show(TenantContext $tenantContext, Post $post): PostResource
    {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        return new PostResource(
            $post->load(['category', 'user', 'tags', 'images'])->loadCount('images')
        );
    }

    public function approve(Request $request, TenantContext $tenantContext, Post $post): JsonResponse
    {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        if (!in_array($post->status, [PostStatus::Draft, PostStatus::Rejected])) {
            return response()->json(['error' => 'Post must be in draft or rejected status'], 422);
        }

        $request->validate([
            'admin_comment' => 'nullable|string|max:2000',
        ]);

        $post->update([
            'status' => PostStatus::Approved,
            'admin_comment' => $request->input('admin_comment', $post->admin_comment),
        ]);

        return response()->json(new PostResource($post->fresh(['category', 'user'])));
    }

    public function publish(
        Request $request,
        TenantContext $tenantContext,
        Post $post,
        WpBridgeService $wpBridge
    ): JsonResponse {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
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
            'published_by' => $request->user()->id,
            'wp_permalink' => $result['permalink'] ?? null,
        ]);

        return response()->json(new PostResource($post->fresh(['category', 'user'])));
    }

    public function reject(Request $request, TenantContext $tenantContext, Post $post): JsonResponse
    {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        if (!in_array($post->status, [PostStatus::Draft, PostStatus::Approved])) {
            return response()->json(['error' => 'Post must be in draft or approved status'], 422);
        }

        $request->validate([
            'admin_comment' => 'nullable|string|max:2000',
        ]);

        $post->update([
            'status' => PostStatus::Rejected,
            'admin_comment' => $request->input('admin_comment', $post->admin_comment),
        ]);

        return response()->json(new PostResource($post->fresh(['category', 'user'])));
    }

    public function updateCategory(Request $request, TenantContext $tenantContext, Post $post): JsonResponse
    {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $post->update(['category_id' => $request->input('category_id')]);

        return response()->json(new PostResource($post->fresh(['category', 'user'])));
    }

    public function destroy(TenantContext $tenantContext, Post $post, WpBridgeService $wpBridge): JsonResponse
    {
        if ($post->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        if ($post->status === PostStatus::Published) {
            return response()->json(['error' => 'Cannot delete published posts'], 422);
        }

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
}
