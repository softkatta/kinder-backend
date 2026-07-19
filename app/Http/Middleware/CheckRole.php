<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $user->loadMissing('roles');

        if (! $user->hasAnyRole($roles)) {
            return ApiResponse::error('Forbidden', 403);
        }

        return $next($request);
    }
}
