<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\IdCard;
use App\Services\IdCard\IdCardPdfService;
use App\Services\IdCard\IdCardService;
use App\Services\IdCard\QrVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdCardController extends Controller
{
    public function __construct(
        private readonly IdCardService $idCardService,
        private readonly IdCardPdfService $pdfService,
        private readonly QrVerificationService $qrService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = IdCard::query()->orderBy('card_type')->orderBy('full_name');

        if ($type = $request->query('type')) {
            $query->where('card_type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('card_number', 'like', "%{$search}%");
            });
        }

        $cards = $query->get()->map(fn (IdCard $c) => $this->idCardService->toCardViewData($c));

        return ApiResponse::success($cards);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $card = $this->idCardService->create($data);

        return ApiResponse::success($this->idCardService->toCardViewData($card), 'ID card created', 201);
    }

    public function show(IdCard $idCard): JsonResponse
    {
        return ApiResponse::success($this->idCardService->toCardViewData($idCard));
    }

    public function update(Request $request, IdCard $idCard): JsonResponse
    {
        $data = $this->validated($request, $idCard->id, partial: true);
        $idCard->update($data);

        return ApiResponse::success($this->idCardService->toCardViewData($idCard->fresh()), 'Updated');
    }

    public function destroy(IdCard $idCard): JsonResponse
    {
        $idCard->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function preview(IdCard $idCard): JsonResponse
    {
        return ApiResponse::success($this->idCardService->toCardViewData($idCard));
    }

    public function print(IdCard $idCard)
    {
        $pdf = $this->pdfService->generatePdf($idCard);

        return $pdf->stream("id-card-{$idCard->card_number}.pdf");
    }

    public function bulkPrint(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:id_cards,id'])['ids'];
        $cards = IdCard::query()->whereIn('id', $ids)->get();
        $pdf = $this->pdfService->generateBulkPdf($cards);

        return $pdf->stream('id-cards-bulk.pdf');
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['qr_token' => 'required|string|max:64']);

        try {
            $result = $this->qrService->verifyOnly($data['qr_token'], $request->user()->id);

            return ApiResponse::success($result, $result['message'] ?? 'Verified');
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Invalid', 422);
        }
    }

    public function scanHistory(Request $request): JsonResponse
    {
        $cardId = $request->query('card_id') ? (int) $request->query('card_id') : null;

        return ApiResponse::success($this->qrService->scanHistory($cardId));
    }

    private function validated(Request $request, ?int $ignoreId = null, bool $partial = false): array
    {
        $rules = [
            'card_type' => [$partial ? 'sometimes' : 'required', Rule::in(IdCard::TYPES)],
            'card_number' => [
                'nullable', 'string', 'max:40',
                Rule::unique('id_cards', 'card_number')->ignore($ignoreId),
            ],
            'status' => ['nullable', Rule::in(IdCard::STATUSES)],
            'full_name' => ($partial ? 'sometimes|' : '').'required|string|max:120',
            'photo_path' => 'nullable|string|max:500',
            'blood_group' => 'nullable|string|max:10',
            'academic_year' => 'nullable|string|max:20',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'emergency_contact' => 'nullable|string|max:120',
            'meta' => 'nullable|array',
            'user_id' => 'nullable|exists:users,id',
        ];

        return $request->validate($rules);
    }
}
