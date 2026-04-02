<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(Tenant $tenant, string $event, array $data): void
    {
        $events = $tenant->notification_events ?? [];

        if (!($events[$event] ?? false)) return;

        if ($tenant->notification_google_chat_webhook) {
            $this->sendGoogleChat($tenant->notification_google_chat_webhook, $event, $data);
        }

        if ($tenant->notification_teams_webhook) {
            $this->sendTeams($tenant->notification_teams_webhook, $event, $data);
        }
    }

    public function sendGoogleChat(string $webhookUrl, string $event, array $data): bool
    {
        $title = $this->eventTitle($event);

        $payload = [
            'cards' => [[
                'header' => [
                    'title' => $title,
                    'subtitle' => 'SakuSaku Post',
                ],
                'sections' => [[
                    'widgets' => $this->buildGoogleChatWidgets($data),
                ]],
            ]],
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Google Chat notification failed: {$e->getMessage()}");
            return false;
        }
    }

    public function sendTeams(string $webhookUrl, string $event, array $data): bool
    {
        $title = $this->eventTitle($event);

        $facts = [];
        foreach (['title' => 'タイトル', 'poster' => '投稿者', 'category' => 'カテゴリ'] as $key => $label) {
            if (isset($data[$key])) {
                $facts[] = ['title' => $label, 'value' => $data[$key]];
            }
        }

        $payload = [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'content' => [
                    'type' => 'AdaptiveCard',
                    'version' => '1.4',
                    'body' => [
                        ['type' => 'TextBlock', 'text' => $title, 'weight' => 'bolder', 'size' => 'medium'],
                        ['type' => 'FactSet', 'facts' => $facts],
                    ],
                ],
            ]],
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, $payload);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning("Teams notification failed: {$e->getMessage()}");
            return false;
        }
    }

    private function eventTitle(string $event): string
    {
        return match ($event) {
            'on_submit' => '新しい記事が投稿されました',
            'on_publish' => '記事が公開されました',
            default => $event,
        };
    }

    private function buildGoogleChatWidgets(array $data): array
    {
        $widgets = [];
        foreach (['title' => 'タイトル', 'poster' => '投稿者', 'category' => 'カテゴリ'] as $key => $label) {
            if (isset($data[$key])) {
                $widgets[] = ['keyValue' => ['topLabel' => $label, 'content' => $data[$key]]];
            }
        }
        return $widgets;
    }
}
