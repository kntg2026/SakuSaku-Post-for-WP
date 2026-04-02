<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $tenantContext): JsonResponse
    {
        $tenantId = $tenantContext->id();
        $startOfMonth = now()->startOfMonth();

        $totalPostsThisMonth = Post::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $pendingCount = Post::where('tenant_id', $tenantId)
            ->whereIn('status', [PostStatus::Pending, PostStatus::Draft])
            ->count();

        $publishedCount = Post::where('tenant_id', $tenantId)
            ->where('status', PostStatus::Published)
            ->where('published_at', '>=', $startOfMonth)
            ->count();

        $activeUsersCount = User::where('tenant_id', $tenantId)
            ->whereHas('posts', fn ($q) => $q->where('created_at', '>=', $startOfMonth))
            ->count();

        return response()->json([
            'total_posts_this_month' => $totalPostsThisMonth,
            'pending_count' => $pendingCount,
            'published_count' => $publishedCount,
            'active_users_count' => $activeUsersCount,
        ]);
    }
}
