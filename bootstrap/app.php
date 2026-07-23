<?php

use SoftKatta\Licensing\Http\Middleware\EnsureInstalled;
use SoftKatta\Licensing\Http\Middleware\EnsureLicenseValid;
use SoftKatta\Licensing\Http\Middleware\EnsureNotInstalled;
use SoftKatta\Licensing\SoftKattaLicensingServiceProvider;
use App\Http\Middleware\EnsureCorsHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hostinger / SPA api-proxy: honour X-Forwarded-Host/Proto for SoftKatta domain binding.
        $middleware->trustProxies(at: '*');

        $apiRateLimit = match (env('APP_ENV', 'production')) {
            'local', 'testing' => env('API_RATE_LIMIT', '1000'),
            default => env('API_RATE_LIMIT', '300'),
        };
        $middleware->throttleApi("{$apiRateLimit},1");
        // Global so CORS headers attach even when API middleware short-circuits.
        $middleware->prepend(EnsureCorsHeaders::class);

        $apiPrepend = [];
        if (env('APP_ENV') !== 'testing') {
            $apiPrepend[] = EnsureInstalled::class;
            $apiPrepend[] = EnsureLicenseValid::class;
        }

        $middleware->api(prepend: $apiPrepend);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'install.not_completed' => EnsureNotInstalled::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        SoftKattaLicensingServiceProvider::schedule($schedule);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $errors = $e->errors();
                $firstMessage = collect($errors)->flatten()->first() ?: 'Validation failed';

                return response()->json([
                    'success' => false,
                    'message' => $firstMessage,
                    'errors' => $errors,
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        // Ensure API error JSON still includes CORS for the SoftKatta SPA origin.
        $exceptions->respond(function ($response, $e, Request $request) {
            if (! $request->is('api/*')) {
                return $response;
            }
            $origin = rtrim((string) $request->headers->get('Origin', ''), '/');
            $allowed = array_map(
                static fn ($o) => rtrim(trim((string) $o), '/'),
                (array) config('cors.allowed_origins', []),
            );
            $frontend = rtrim((string) config('app.frontend_url'), '/');
            if ($frontend !== '') {
                $allowed[] = $frontend;
            }
            if ($origin !== '' && in_array($origin, $allowed, true) && ! $response->headers->has('Access-Control-Allow-Origin')) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Vary', 'Origin');
            }

            return $response;
        });
    })->create();
