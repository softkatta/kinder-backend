<?php

namespace App\Services\IdCard;

use App\Models\IdCard;
use Barryvdh\DomPDF\Facade\Pdf;

class IdCardPdfService
{
    public function __construct(
        private readonly IdCardService $idCardService,
    ) {}

    public function generatePdf(IdCard $card)
    {
        $data = $this->idCardService->toCardViewData($card);

        return Pdf::loadView('pdf.id-card', ['card' => $data])
            ->setPaper([0, 0, 242.65, 153.07], 'landscape'); // CR80 mm → points (85.6×53.98mm)
    }

    public function generateBulkPdf(iterable $cards)
    {
        $items = collect($cards)->map(fn (IdCard $c) => $this->idCardService->toCardViewData($c));

        return Pdf::loadView('pdf.id-card-bulk', ['cards' => $items])
            ->setPaper('a4', 'portrait');
    }
}
