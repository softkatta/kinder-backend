<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (app()->environment('production')) {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return response()
            ->view('api-home', [
                'appName' => config('app.name', 'Kindergarten API'),
                'frontend' => $frontend !== '' ? $frontend : null,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    return response()->json([
        'success' => true,
        'message' => 'Kindergarten API',
        'data' => [
            'app' => config('app.name'),
            'env' => config('app.env'),
            'frontend' => config('app.frontend_url'),
            'health' => url('/up'),
            'api' => url('/api/v1/health'),
        ],
    ]);
});
