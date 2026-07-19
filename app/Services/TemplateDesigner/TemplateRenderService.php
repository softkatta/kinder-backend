<?php

namespace App\Services\TemplateDesigner;

use App\Models\Template;

class TemplateRenderService
{
    private const MM_TO_PT = 72 / 25.4;

    public function __construct(
        private readonly VariableResolverService $variables,
    ) {}

    /** @param array<string, string> $data */
    public function renderHtml(Template $template, array $data, bool $forPdf = false): string
    {
        $canvas = $template->canvas_json ?? CanvasDefaults::empty($template->paper_size);

        [$pageW, $pageH] = CanvasDefaults::dimensions($template->paper_size);

        $bgHtml = '';
        if ($template->background_image) {
            $src = $this->resolveImageSrc($template->background_image, $forPdf);
            if ($src !== '') {
                $bgHtml = $this->renderBackgroundImage($src, $pageW, $pageH, $forPdf);
            }
        }

        $objects = $canvas['objects'] ?? [];
        $body = '';
        foreach ($objects as $obj) {
            $body .= $this->renderObject($obj, $data, $forPdf);
        }

        if ($forPdf) {
            return $bgHtml.$body;
        }

        return '<div class="td-page" style="width:'.$pageW.'mm;height:'.$pageH.'mm;position:relative;overflow:hidden;">'
            .$bgHtml
            .$body
            .'</div>';
    }

    /** @return array{0: float, 1: float} page size in pt */
    public function pageSizePt(string $paperSize): array
    {
        [$pageW, $pageH] = CanvasDefaults::dimensions($paperSize);

        return [$this->mm($pageW), $this->mm($pageH)];
    }

    /** @param array<string, mixed> $obj
     * @param array<string, string> $data
     */
    private function renderObject(array $obj, array $data, bool $forPdf = false): string
    {
        $objectType = (string) ($obj['objectType'] ?? 'variable');
        $type = (string) ($obj['dataType'] ?? 'text');
        $x = (float) ($obj['x'] ?? 0);
        $y = (float) ($obj['y'] ?? 0);
        $w = (float) ($obj['width'] ?? 40);
        $h = (float) ($obj['height'] ?? 10);
        $rot = (float) ($obj['rotation'] ?? 0);

        if ($objectType === 'asset' || $type === 'asset') {
            $path = (string) ($obj['imagePath'] ?? '');
            if ($path === '') {
                return '';
            }

            return $this->renderImageBox($this->resolveImageSrc($path, $forPdf), $x, $y, $w, $h, $rot, $forPdf);
        }

        if ($objectType === 'line' || $type === 'line') {
            return $this->renderLine($obj, $x, $y, $w, $h, $rot, $forPdf);
        }

        if ($objectType === 'grid' || $type === 'grid') {
            return $this->wrapBox($this->renderGrid($obj, $forPdf), $x, $y, $w, $h, $rot, $forPdf, false);
        }

        $key = (string) ($obj['variableKey'] ?? '');

        if (in_array($type, ['image', 'photo', 'logo', 'signature'], true) || in_array($key, ['photo', 'logo', 'principal_signature', 'qr_code'], true)) {
            $src = $data[$key] ?? '';
            if (! $src) {
                return '';
            }

            return $this->renderImageBox($this->resolveImageSrc($src, $forPdf, allowRemote: true), $x, $y, $w, $h, $rot, $forPdf);
        }

        if (in_array($key, ['marks_table', 'attendance'], true) || $type === 'table') {
            return $this->wrapBox($data[$key] ?? '', $x, $y, $w, $h, $rot, $forPdf, false);
        }

        $text = $data[$key] ?? '';
        if ($text === '' && ! $forPdf) {
            $text = '{{'.$key.'}}';
        }
        if ($text === '') {
            return '';
        }

        $font = $this->pdfFont((string) ($obj['fontFamily'] ?? 'DejaVu Sans'), $forPdf);
        $size = (float) ($obj['fontSize'] ?? 12);
        $color = e((string) ($obj['color'] ?? '#111111'));
        $align = e((string) ($obj['textAlign'] ?? 'left'));
        $weight = ($obj['bold'] ?? false) ? '700' : '400';
        $italic = ($obj['italic'] ?? false) ? 'italic' : 'normal';
        $underline = ($obj['underline'] ?? false) ? 'underline' : 'none';

        $inner = '<div style="font-family:'.$font.';font-size:'.$this->fontSizeCss($size, $forPdf).';color:'.$color.';text-align:'.$align.';font-weight:'.$weight.';font-style:'.$italic.';text-decoration:'.$underline.';line-height:1.2;word-wrap:break-word;overflow-wrap:break-word;white-space:normal;">'.e($text).'</div>';

        return $this->wrapBox($inner, $x, $y, $w, $h, $rot, $forPdf, true);
    }

    private function fontSizeCss(float $size, bool $forPdf): string
    {
        // Canvas editor treats fontSize as CSS px at 100% zoom; screen/print HTML must match.
        return $forPdf ? $size.'pt' : $size.'px';
    }

    private function pdfFont(string $font, bool $forPdf): string
    {
        if (! $forPdf) {
            return e($font);
        }

        return match ($font) {
            'Georgia', 'Times New Roman', 'DejaVu Serif' => 'DejaVu Serif',
            'Arial' => 'DejaVu Sans',
            default => e($font),
        };
    }

    private function renderBackgroundImage(string $src, float $pageW, float $pageH, bool $forPdf): string
    {
        if ($forPdf) {
            return sprintf(
                '<img src="%s" style="position:fixed;left:0;top:0;width:%.2fpt;height:%.2fpt;z-index:0;" alt="" />',
                e($src),
                $this->mm($pageW),
                $this->mm($pageH),
            );
        }

        return '<img src="'.e($src).'" alt="" style="position:absolute;left:0;top:0;width:100%;height:100%;object-fit:fill;z-index:0;" />';
    }

    private function renderImageBox(string $src, float $x, float $y, float $w, float $h, float $rot, bool $forPdf): string
    {
        if ($forPdf) {
            $style = sprintf(
                'position:fixed;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;z-index:2;',
                $this->mm($x),
                $this->mm($y),
                $this->mm($w),
                $this->mm($h),
            );

            return sprintf('<img src="%s" style="%s" alt="" />', e($src), $style);
        }

        $style = $this->webBoxStyle($x, $y, $w, $h, $rot);

        return '<div style="'.$style.'"><img src="'.e($src).'" style="width:100%;height:100%;object-fit:contain;display:block;" alt="" /></div>';
    }

    private function wrapBox(string $inner, float $x, float $y, float $w, float $h, float $rot, bool $forPdf, bool $isText): string
    {
        if ($inner === '') {
            return '';
        }

        $style = $forPdf
            ? ($isText ? $this->pdfTextBoxStyle($x, $y, $w) : $this->pdfBoxStyle($x, $y, $w, $h))
            : ($isText ? $this->webTextBoxStyle($x, $y, $w, $h, $rot) : $this->webBoxStyle($x, $y, $w, $h, $rot));

        return '<div style="'.$style.'">'.$inner.'</div>';
    }

    /** @param array<string, mixed> $obj */
    private function renderLine(array $obj, float $x, float $y, float $w, float $h, float $rot, bool $forPdf): string
    {
        $color = e((string) ($obj['color'] ?? '#111111'));
        $thickness = max(0.1, (float) ($obj['lineThickness'] ?? 0.4));
        $direction = (string) ($obj['lineDirection'] ?? 'horizontal');
        $lineStyle = (string) ($obj['lineStyle'] ?? 'solid');
        $pos = $forPdf ? 'fixed' : 'absolute';

        if ($forPdf) {
            $t = $this->mm($thickness);
            $left = $this->mm($x);
            $top = $this->mm($y);
            $width = $this->mm($w);
            $height = $this->mm($h);

            if ($direction === 'vertical') {
                $border = sprintf('border-left:%.2fpt %s %s;height:%.2fpt;width:0;', $t, $lineStyle, $color, $height);

                return sprintf('<div style="position:%s;left:%.2fpt;top:%.2fpt;%s;z-index:2;"></div>', $pos, $left, $top, $border);
            }

            $border = sprintf('border-top:%.2fpt %s %s;width:%.2fpt;height:0;', $t, $lineStyle, $color, $width);

            return sprintf('<div style="position:%s;left:%.2fpt;top:%.2fpt;%s;z-index:2;"></div>', $pos, $left, $top + ($height / 2), $border);
        }

        $style = $this->webBoxStyle($x, $y, $w, $h, $rot);
        if ($direction === 'vertical') {
            $inner = '<div style="width:0;height:100%;border-left:'.$thickness.'mm '.$lineStyle.' '.$color.';margin:0 auto;"></div>';
        } else {
            $inner = '<div style="width:100%;height:0;border-top:'.$thickness.'mm '.$lineStyle.' '.$color.';margin-top:50%;"></div>';
        }

        return '<div style="'.$style.'">'.$inner.'</div>';
    }

    /** @param array<string, mixed> $obj */
    private function renderGrid(array $obj, bool $forPdf = false): string
    {
        $rows = max(1, (int) ($obj['gridRows'] ?? 4));
        $cols = max(1, (int) ($obj['gridCols'] ?? 4));
        $headers = is_array($obj['gridHeaders'] ?? null) ? $obj['gridHeaders'] : [];
        $showHeader = (bool) ($obj['gridShowHeader'] ?? true);
        $borderColor = e((string) ($obj['borderColor'] ?? '#94a3b8'));
        $borderWidth = max(0.1, (float) ($obj['borderWidth'] ?? 0.3));
        $fontSize = max(6, (float) ($obj['cellFontSize'] ?? 9));
        $border = $forPdf
            ? sprintf('%.2fpt solid %s', $this->mm($borderWidth), $borderColor)
            : $borderWidth.'mm solid '.$borderColor;

        $html = '<table class="td-table" style="width:100%;border-collapse:collapse;font-size:'.$fontSize.'pt;">';

        if ($showHeader) {
            $html .= '<tr>';
            for ($c = 0; $c < $cols; $c++) {
                $label = e((string) ($headers[$c] ?? 'Col '.($c + 1)));
                $html .= '<th style="border:'.$border.';padding:3px 5px;background:#f1f5f9;">'.$label.'</th>';
            }
            $html .= '</tr>';
            $rows = max(0, $rows - 1);
        }

        for ($r = 0; $r < $rows; $r++) {
            $html .= '<tr>';
            for ($c = 0; $c < $cols; $c++) {
                $html .= '<td style="border:'.$border.';padding:3px 5px;">&nbsp;</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</table>';
    }

    private function pdfBoxStyle(float $x, float $y, float $w, float $h): string
    {
        return sprintf(
            'position:fixed;left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;overflow:hidden;z-index:2;',
            $this->mm($x),
            $this->mm($y),
            $this->mm($w),
            $this->mm($h),
        );
    }

    private function pdfTextBoxStyle(float $x, float $y, float $w): string
    {
        return sprintf(
            'position:fixed;left:%.2fpt;top:%.2fpt;width:%.2fpt;z-index:3;overflow:visible;',
            $this->mm($x),
            $this->mm($y),
            $this->mm($w),
        );
    }

    private function webBoxStyle(float $x, float $y, float $w, float $h, float $rot): string
    {
        return "position:absolute;left:{$x}mm;top:{$y}mm;width:{$w}mm;height:{$h}mm;transform:rotate({$rot}deg);transform-origin:left top;z-index:1;overflow:hidden;";
    }

    private function webTextBoxStyle(float $x, float $y, float $w, float $h, float $rot): string
    {
        return "position:absolute;left:{$x}mm;top:{$y}mm;width:{$w}mm;min-height:{$h}mm;transform:rotate({$rot}deg);transform-origin:left top;z-index:2;overflow:visible;";
    }

    private function mm(float $mm): float
    {
        return $mm * self::MM_TO_PT;
    }

    private function resolveImageSrc(string $path, bool $forPdf, bool $allowRemote = false): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            if ($forPdf) {
                return $this->toDataUri($path);
            }

            return $path;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, 8);
        }

        $local = public_path('storage/'.$relative);

        if ($forPdf && is_file($local)) {
            return $this->fileToDataUri($local);
        }

        return '/storage/'.$relative;
    }

    private function toDataUri(string $url): string
    {
        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $local = public_path(ltrim($path, '/'));
            if (is_file($local)) {
                return $this->fileToDataUri($local);
            }
        }

        return $url;
    }

    private function fileToDataUri(string $localPath): string
    {
        $mime = mime_content_type($localPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($localPath));
    }

    public function css(bool $forPdf = false, float $pageWpt = 0, float $pageHpt = 0): string
    {
        if ($forPdf) {
            return sprintf(
                '@page { margin: 0; size: %.2fpt %.2fpt; } html, body { margin: 0; padding: 0; width: %.2fpt; height: %.2fpt; overflow: hidden; }',
                $pageWpt,
                $pageHpt,
                $pageWpt,
                $pageHpt,
            ).<<<'CSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
.td-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
.td-table th, .td-table td { border: 1px solid #94a3b8; padding: 4px 6px; word-wrap: break-word; }
.td-table th { background: #f1f5f9; }
img { max-width: none; }
CSS;
        }

        return <<<'CSS'
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { margin: 0; padding: 0; }
body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.td-page { page-break-after: avoid; page-break-inside: avoid; position: relative; overflow: hidden; }
.td-table { width: 100%; border-collapse: collapse; font-size: 9pt; table-layout: fixed; }
.td-table th, .td-table td { border: 1px solid #94a3b8; padding: 4px 6px; word-wrap: break-word; }
.td-table th { background: #f1f5f9; }
img { max-width: none; }
CSS;
    }
}
