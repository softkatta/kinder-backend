<?php

namespace App\Services\TemplateDesigner;

class CanvasDefaults
{
    /** @return array<string, mixed> */
    public static function empty(string $paperSize = 'a4_portrait'): array
    {
        [$w, $h] = self::dimensions($paperSize);

        return [
            'version' => 1,
            'settings' => [
                'width' => $w,
                'height' => $h,
                'unit' => 'mm',
                'gridSize' => 5,
                'snapToGrid' => true,
                'showGrid' => false,
            ],
            'objects' => [],
        ];
    }

    /** @return array{0: float, 1: float} */
    public static function dimensions(string $paperSize): array
    {
        return match ($paperSize) {
            'a4_landscape' => [297.0, 210.0],
            default => [210.0, 297.0],
        };
    }
}
