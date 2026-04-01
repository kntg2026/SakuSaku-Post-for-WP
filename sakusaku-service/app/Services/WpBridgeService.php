<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

class WpBridgeService
{
    public function __construct(private TenantContext $tenantContext) {}

    private function request(string $method, string $path, array $data = []): array
    {
        $tenant = $this->tenantContext->get();
        $baseUrl = rtrim($tenant->wp_site_url, '/');

        // Use ?rest_route= format for compatibility
        $url = "{$baseUrl}/?rest_route=/sakusaku/v1{$path}";

        $response = Http::timeout(30)
            ->withHeaders(['X-Sakusaku-Api-Key' => $tenant->wp_api_key])
            ->$method($url, $data);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "WP Bridge error [{$response->status()}]: " . $response->body()
            );
        }

        return $response->json() ?? [];
    }

    public function ping(): array
    {
        return $this->request('get', '/ping');
    }

    public function createDraft(array $postData): array
    {
        return $this->request('post', '/posts', $postData);
    }

    public function updatePost(int $wpPostId, array $data): array
    {
        return $this->request('put', "/posts/{$wpPostId}", $data);
    }

    public function publishPost(int $wpPostId): array
    {
        return $this->request('post', "/posts/{$wpPostId}/publish");
    }

    public function unpublishPost(int $wpPostId): array
    {
        return $this->request('post', "/posts/{$wpPostId}/unpublish");
    }

    public function deletePost(int $wpPostId): array
    {
        return $this->request('delete', "/posts/{$wpPostId}");
    }

    public function uploadImage(array $fileData): array
    {
        return $this->request('post', '/media', $fileData);
    }

    public function setFeaturedImage(int $wpPostId, int $attachmentId): array
    {
        return $this->request('post', "/posts/{$wpPostId}/thumbnail", [
            'attachment_id' => $attachmentId,
        ]);
    }

    public function getCategories(): array
    {
        return $this->request('get', '/categories');
    }

    public function createCategory(string $name, int $parent = 0): array
    {
        return $this->request('post', '/categories', [
            'name' => $name,
            'parent' => $parent,
        ]);
    }

    public function createTag(string $name, ?int $postId = null): array
    {
        return $this->request('post', '/tags', [
            'name' => $name,
            'post_id' => $postId,
        ]);
    }
}
