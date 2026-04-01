<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->validate(['tenant' => 'required|string|exists:tenants,slug']);

        session(['auth_tenant_slug' => $request->input('tenant')]);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['state' => $request->input('tenant')])
            ->redirect();
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
    {
        $tenantSlug = session('auth_tenant_slug') ?? $request->input('state');

        if (!$tenantSlug) {
            return response()->json(['error' => 'Tenant not specified'], 400);
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if (!$tenant->isActive()) {
            return response()->json(['error' => 'Tenant is not active'], 403);
        }

        $googleUser = Socialite::driver('google')->user();

        $user = User::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'google_id' => $googleUser->getId(),
            ],
            [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'avatar_url' => $googleUser->getAvatar(),
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? null,
                'google_token_expires_at' => $googleUser->expiresIn
                    ? now()->addSeconds($googleUser->expiresIn)
                    : null,
                'last_login_at' => now(),
            ]
        );

        $token = $user->createToken('auth', ['*'], now()->addDays(30));

        session()->forget('auth_tenant_slug');

        // Redirect to SPA with token
        $params = http_build_query([
            'token' => $token->plainTextToken,
            'user' => json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'level' => $user->level->value,
                'avatar_url' => $user->avatar_url,
            ]),
        ]);

        return redirect(config('app.url') . "/auth/callback?{$params}");
    }
}
