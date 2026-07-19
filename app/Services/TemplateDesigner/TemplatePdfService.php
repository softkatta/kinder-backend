<?php

namespace App\Services\TemplateDesigner;

use App\Models\ExamResult;
use App\Models\IdCard;
use App\Models\Template;
use Barryvdh\DomPDF\Facade\Pdf;

class TemplatePdfService
{
    public function __construct(
        private readonly TemplateRenderService $renderer,
        private readonly VariableResolverService $variables,
    ) {}

    /** @param array<string, string> $data */
    public function make(Template $template, array $data)
    {
        [$pageWpt, $pageHpt] = $this->renderer->pageSizePt($template->paper_size);
        $html = $this->renderer->renderHtml($template, $data, forPdf: true);
        $css = $this->renderer->css(forPdf: true, pageWpt: $pageWpt, pageHpt: $pageHpt);
        $bodyStyle = sprintf('margin:0;padding:0;width:%.2fpt;height:%.2fpt;overflow:hidden;', $pageWpt, $pageHpt);
        $full = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'.$css.'</style></head>'
            .'<body style="'.$bodyStyle.'">'.$html.'</body></html>';

        [$w, $h] = CanvasDefaults::dimensions($template->paper_size);
        $pt = 72 / 25.4;
        $short = min($w, $h) * $pt;
        $long = max($w, $h) * $pt;
        $orientation = $w > $h ? 'landscape' : 'portrait';

        return Pdf::loadHTML($full)
            ->setPaper([0, 0, $short, $long], $orientation)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');
    }

    public function forStudent(Template $template, IdCard $student, ?ExamResult $examResult = null)
    {
        $template->loadMissing('category');

        return $this->make($template, $this->variables->resolve($student, $examResult, $template));
    }
}
