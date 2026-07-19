<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Never expose API metadata on the bare host in production.
    if (app()->environment('production')) {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($frontend !== '' && $frontend !== rtrim((string) config('app.url'), '/')) {
            return redirect()->away($frontend);
        }

        abort(404);
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
