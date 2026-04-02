<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocsRetrievalMethod;
use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use App\Services\WpBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantSettingsController extends Controller
{
    public function show(TenantContext $tenantContext): JsonResponse
    {
        $tenant = $tenantContext->get();

        return response()->json([
            'wp_site_url' => $tenant->wp_site_url,
            'wp_api_key' => $tenant->wp_api_key,
            'docs_retrieval_method' => $tenant->docs_retrieval_method->value,
            'settings' => $tenant->settings ?? [],
        ]);
    }

    public function update(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $request->validate([
            'docs_retrieval_method' => [
                'sometimes',
                Rule::in(array_column(DocsRetrievalMethod::cases(), 'value')),
            ],
            'settings' => 'sometimes|array',
        ]);

        $tenant = $tenantContext->get();
        $data = [];

        if ($request->has('docs_retrieval_method')) {
            $data['docs_retrieval_method'] = $request->input('docs_retrieval_method');
        }

        if ($request->has('settings')) {
            $data['settings'] = array_merge($tenant->settings ?? [], $request->input('settings'));
        }

        if (!empty($data)) {
            $tenant->update($data);
        }

        return response()->json(['message' => 'Settings updated']);
    }

    public function testWpConnection(WpBridgeService $wpBridge): JsonResponse
    {
        try {
            $result = $wpBridge->ping();
            return response()->json([
                'success' => true,
                'message' => 'WordPress connection successful',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'WordPress connection failed: ' . $e->getMessage(),
            ], 502);
        }
    }
}
