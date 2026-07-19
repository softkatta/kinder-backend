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
        $apiRateLimit = match (env('APP_ENV', 'production')) {
            'local', 'testing' => env('API_RATE_LIMIT', '1000'),
            default => env('API_RATE_LIMIT', '300'),
        };
        $middleware->throttleApi("{$apiRateLimit},1");
        $middleware->api(prepend: [
            EnsureCorsHeaders::class,
            EnsureInstalled::class,
            EnsureLicenseValid::class,
        ]);
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
    })->create();
