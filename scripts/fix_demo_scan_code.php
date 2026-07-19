<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$guest = App\Models\Guest::where('qr_token', 'LS-GUEST-DEMO001')->first();
if ($guest) {
    $guest->update(['scan_code' => 'demoguestscan01']);
    echo "Demo guest scan URL: ".app(App\Services\QrScanResolver::class)->scanUrl('demoguestscan01').PHP_EOL;
}
