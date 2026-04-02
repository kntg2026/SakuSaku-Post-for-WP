<?php

namespace App\Services;

use App\Enums\DocsRetrievalMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleDocsService
{
    public function __construct(private TenantContext $tenantContext) {}

    public function fetchDocument(string $docId, ?User $user = null): DocsResult
    {
        $tenant = $this->tenantContext->get();

        $html = match ($tenant->docs_retrieval_method) {
            DocsRetrievalMethod::UrlDirect => $this->fetchViaUrl($docId),
            DocsRetrievalMethod::OAuth => $this->fetchViaOAuth($docId, $user),
            DocsRetrievalMethod::ServiceAccount => $this->fetchViaServiceAccount($docId, $tenant),
        };

        return new DocsResult(html: $html);
    }

    private function fetchViaUrl(string $docId): string
    {
        $url = "https://docs.google.com/document/d/{$docId}/export?format=html";
        $response = Http::retry(2, 1000)->timeout(30)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch doc via URL: HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function fetchViaOAuth(string $docId, ?User $user): string
    {
        if (!$user) {
            throw new \RuntimeException('User required for OAuth retrieval method');
        }

        $accessToken = $this->getValidOAuthToken($user);
        $url = "https://www.googleapis.com/drive/v3/files/{$docId}/export?mimeType=text/html";

        $response = Http::retry(2, 1000)->timeout(30)
            ->withToken($accessToken)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch doc via OAuth: HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function fetchViaServiceAccount(string $docId, Tenant $tenant): string
    {
        $credentials = $tenant->gcp_credentials;

        if (!$credentials || !isset($credentials['client_email'], $credentials['private_key'])) {
            throw new \RuntimeException('Service account credentials not configured');
        }

        $accessToken = $this->getServiceAccountToken($credentials);
        $url = "https://www.googleapis.com/drive/v3/files/{$docId}/export?mimeType=text/html";

        $response = Http::retry(2, 1000)->timeout(30)
            ->withToken($accessToken)
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch doc via SA: HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function getValidOAuthToken(User $user): string
    {
        // 有効期限の5分前にリフレッシュ（ジョブ遅延によるギリギリ切れを防ぐ）
        $margin = now()->addMinutes(5);
        if ($user->google_token_expires_at && $user->google_token_expires_at->isAfter($margin)) {
            return $user->google_access_token;
        }

        if (!$user->google_refresh_token) {
            throw new \RuntimeException('OAuth token expired and no refresh token available');
        }

        Log::info("GoogleDocs: Refreshing OAuth token for user {$user->id}");

        $response = Http::retry(2, 1000)->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
        ]);

        if (!$response->successful()) {
            Log::error("GoogleDocs: Token refresh failed for user {$user->id}: HTTP {$response->status()}");
            throw new \RuntimeException("Failed to refresh OAuth token: HTTP {$response->status()}");
        }

        $data = $response->json();
        $user->update([
            'google_access_token' => $data['access_token'],
            'google_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
        ]);

        Log::info("GoogleDocs: Token refreshed for user {$user->id}, expires in {$data['expires_in']}s");

        return $data['access_token'];
    }

    private function getServiceAccountToken(array $credentials): string
    {
        $now = time();
        $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64url_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign("{$header}.{$claim}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = "{$header}.{$claim}." . base64url_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to get service account token');
        }

        return $response->json('access_token');
    }

    public static function extractDocId(string $url): ?string
    {
        if (preg_match('#/document/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}

class DocsResult
{
    public readonly string $title;
    public readonly string $html;

    public function __construct(string $html)
    {
        $this->html = $html;
        $this->title = '';
    }
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
