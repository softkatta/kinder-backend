<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Services\Guest\GuestService;
use Illuminate\Database\Seeder;

class GuestPortalSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(GuestService::class);

        Guest::query()->whereNull('user_id')->each(function (Guest $guest) use ($service) {
            $service->syncPortalUser($guest);
        });
    }
}
