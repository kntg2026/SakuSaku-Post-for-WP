<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserLevel
{
    public function handle(Request $request, Closure $next, int $minLevel): Response
    {
        if (!$request->user()?->hasMinLevel($minLevel)) {
            return response()->json([
                'error' => "Level {$minLevel} or higher required",
            ], 403);
        }

        return $next($request);
    }
}
