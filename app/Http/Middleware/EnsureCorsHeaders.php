<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures CORS headers are present on API responses even when Hostinger/WAF
 * or early middleware returns an error (browsers otherwise report a CORS failure).
 */
class EnsureCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*') && ! $request->is('sanctum/csrf-cookie') && ! $request->is('broadcasting/auth')) {
            return $response;
        }

        $origin = (string) $request->headers->get('Origin', '');
        if ($origin === '') {
            return $response;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($o): string => rtrim(trim((string) $o), '/'),
            (array) config('cors.allowed_origins', []),
        )));

        $frontend = rtrim((string) config('app.frontend_url'), '/');
        if ($frontend !== '' && ! in_array($frontend, $allowed, true)) {
            $allowed[] = $frontend;
        }

        // SoftKatta production SPA hosts — always allow even if .env CORS list is incomplete.
        foreach (['https://kinder.softkatta.in', 'https://www.kinder.softkatta.in'] as $hardcoded) {
            if (! in_array($hardcoded, $allowed, true)) {
                $allowed[] = $hardcoded;
            }
        }

        $originNorm = rtrim($origin, '/');
        if (! in_array($originNorm, $allowed, true)) {
            return $response;
        }

        if (! $response->headers->has('Access-Control-Allow-Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $originNorm);
        }
        if (! $response->headers->has('Access-Control-Allow-Credentials')) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        if (! $response->headers->has('Access-Control-Allow-Methods')) {
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        }
        if (! $response->headers->has('Access-Control-Allow-Headers')) {
            $response->headers->set(
                'Access-Control-Allow-Headers',
                'Authorization, Content-Type, Accept, X-Requested-With, X-XSRF-TOKEN'
            );
        }
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }
}
