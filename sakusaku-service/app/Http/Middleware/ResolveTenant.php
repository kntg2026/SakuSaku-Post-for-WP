<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
