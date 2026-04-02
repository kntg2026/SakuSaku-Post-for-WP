<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\TenantContext;
use App\Services\WpBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryManagementController extends Controller
{
    public function index(TenantContext $tenantContext): AnonymousResourceCollection
    {
        $categories = Category::where('tenant_id', $tenantContext->id())
            ->withCount('posts')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ]);

        $category = Category::create([
            'tenant_id' => $tenantContext->id(),
            'name' => $request->input('name'),
            'slug' => $request->input('slug', \Str::slug($request->input('name'))),
            'parent_id' => $request->input('parent_id'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => true,
        ]);

        return response()->json(new CategoryResource($category), 201);
    }

    public function update(Request $request, TenantContext $tenantContext, Category $category): JsonResponse
    {
        if ($category->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($request->only(['name', 'slug', 'parent_id', 'sort_order', 'is_active']));

        return response()->json(new CategoryResource($category->fresh()));
    }

    public function destroy(TenantContext $tenantContext, Category $category): JsonResponse
    {
        if ($category->tenant_id !== $tenantContext->id()) {
            abort(404);
        }

        if ($category->posts()->exists()) {
            return response()->json(['error' => 'Cannot delete category with posts'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    public function syncFromWp(TenantContext $tenantContext, WpBridgeService $wpBridge): JsonResponse
    {
        $tenantId = $tenantContext->id();
        $wpCategories = $wpBridge->getCategories();

        $synced = 0;
        foreach ($wpCategories as $wpCat) {
            Category::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'wp_category_id' => $wpCat['id'],
                ],
                [
                    'name' => $wpCat['name'],
                    'slug' => $wpCat['slug'] ?? \Str::slug($wpCat['name']),
                    'is_active' => true,
                ]
            );
            $synced++;
        }

        return response()->json([
            'message' => "Synced {$synced} categories from WordPress",
            'synced_count' => $synced,
        ]);
    }
}
