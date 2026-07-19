<?php

namespace App\Services\IdCard;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeGenerator
{
    /** Generate SVG QR (no imagick required) — print-ready with quiet zone */
    public function svg(string $payload, int $size = 220): string
    {
        return QrCode::format('svg')
            ->size($size)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($payload);
    }

    /** Base64 data URI for embedding in PDF/HTML */
    public function dataUri(string $payload, int $size = 220): string
    {
        $svg = $this->svg($payload, $size);
        $encoded = base64_encode($svg);

        return 'data:image/svg+xml;base64,'.$encoded;
    }
}
