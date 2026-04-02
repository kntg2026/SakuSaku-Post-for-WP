<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotificationSettingsController extends Controller
{
    public function show(TenantContext $tenantContext): JsonResponse
    {
        $tenant = $tenantContext->get();

        return response()->json([
            'google_chat_webhook' => $tenant->notification_google_chat_webhook,
            'teams_webhook' => $tenant->notification_teams_webhook,
            'events' => $tenant->notification_events ?? [
                'on_submit' => false,
                'on_publish' => false,
            ],
        ]);
    }

    public function update(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $request->validate([
            'google_chat_webhook' => 'nullable|url|max:1000',
            'teams_webhook' => 'nullable|url|max:1000',
            'events' => 'required|array',
            'events.on_submit' => 'required|boolean',
            'events.on_publish' => 'required|boolean',
        ]);

        $tenant = $tenantContext->get();
        $tenant->update([
            'notification_google_chat_webhook' => $request->input('google_chat_webhook'),
            'notification_teams_webhook' => $request->input('teams_webhook'),
            'notification_events' => $request->input('events'),
        ]);

        return response()->json(['message' => 'Notification settings updated']);
    }

    public function test(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:google_chat,teams',
        ]);

        $tenant = $tenantContext->get();
        $type = $request->input('type');

        if ($type === 'google_chat') {
            $url = $tenant->notification_google_chat_webhook;
            if (!$url) {
                return response()->json(['error' => 'Google Chat webhook URL not set'], 422);
            }
            $response = Http::post($url, ['text' => 'SakuSaku Post テスト通知']);
        } else {
            $url = $tenant->notification_teams_webhook;
            if (!$url) {
                return response()->json(['error' => 'Teams webhook URL not set'], 422);
            }
            $response = Http::post($url, ['text' => 'SakuSaku Post テスト通知']);
        }

        if ($response->successful()) {
            return response()->json(['message' => 'Test notification sent']);
        }

        return response()->json(['error' => 'Failed to send test notification'], 502);
    }
}
