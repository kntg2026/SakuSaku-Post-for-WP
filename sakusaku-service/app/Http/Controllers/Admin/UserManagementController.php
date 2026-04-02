<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(TenantContext $tenantContext): JsonResponse
    {
        $users = User::where('tenant_id', $tenantContext->id())
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'level' => $user->level->value,
                'avatar_url' => $user->avatar_url,
                'posts_count' => $user->posts_count,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $users]);
    }

    public function updateLevel(Request $request, TenantContext $tenantContext, User $user): JsonResponse
    {
        if ($user->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        $request->validate([
            'level' => ['required', 'integer', Rule::in([1, 2, 3])],
        ]);

        $user->update(['level' => UserLevel::from($request->input('level'))]);

        return response()->json([
            'id' => $user->id,
            'level' => $user->level->value,
            'message' => 'Level updated',
        ]);
    }

    public function updateRole(Request $request, TenantContext $tenantContext, User $user): JsonResponse
    {
        if ($user->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        // Prevent removing admin role from self
        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot change your own role'], 422);
        }

        $request->validate([
            'role' => ['required', Rule::in(['poster', 'admin'])],
        ]);

        $user->update(['role' => UserRole::from($request->input('role'))]);

        return response()->json([
            'id' => $user->id,
            'role' => $user->role->value,
            'message' => 'Role updated',
        ]);
    }
}
