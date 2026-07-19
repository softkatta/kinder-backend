<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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
