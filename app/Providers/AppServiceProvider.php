<?php

namespace App\Providers;

use App\Services\SchoolTimezone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SchoolTimezone::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'softkatta.seat_usage_resolver' => static function (): array {
                return [
                    'users' => \App\Models\User::query()->count(),
                    'students' => \App\Models\IdCard::query()->where('card_type', 'student')->count(),
                ];
            },
        ]);

        try {
            $this->app->make(SchoolTimezone::class)->apply();
        } catch (\Throwable) {
            // DB may be unavailable during install / migrate
        }
    }
}
