<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\IdCard;
use App\Services\QrScanResolver;
use Illuminate\Database\Seeder;

class ScanCodeSeeder extends Seeder
{
    public function run(): void
    {
        $resolver = app(QrScanResolver::class);

        Guest::query()->whereNull('scan_code')->each(function (Guest $guest) use ($resolver) {
            $guest->update(['scan_code' => $resolver->generateScanCode()]);
        });

        IdCard::query()->whereNull('scan_code')->each(function (IdCard $card) use ($resolver) {
            $card->update(['scan_code' => $resolver->generateScanCode()]);
        });
    }
}
