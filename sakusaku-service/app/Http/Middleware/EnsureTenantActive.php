<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->tenantContext->isResolved()) {
            return response()->json(['error' => 'Tenant not resolved'], 403);
        }

        if (!$this->tenantContext->get()->isActive()) {
            return response()->json([
                'error' => 'Tenant is not active',
                'status' => $this->tenantContext->get()->status->value,
            ], 403);
        }

        return $next($request);
    }
}
