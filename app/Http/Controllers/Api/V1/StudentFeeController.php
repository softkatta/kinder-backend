<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FeeCategory;
use App\Models\IdCard;
use App\Models\StudentFee;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentFeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StudentFee::query()
            ->with(['idCard:id,full_name,card_number', 'feeCategory:id,name,code'])
            ->latest('due_date');

        if ($idCardId = $request->query('id_card_id')) {
            $query->where('id_card_id', $idCardId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success($query->get()->map(fn (StudentFee $fee) => $this->toRow($fee)));
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_card_id' => ['required', 'exists:id_cards,id'],
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'title' => ['required_without:fee_category_id', 'string', 'max:160'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $card = IdCard::query()->findOrFail($data['id_card_id']);
        $category = isset($data['fee_category_id'])
            ? FeeCategory::query()->find($data['fee_category_id'])
            : null;

        $amount = (float) ($data['amount'] ?? $category?->amount ?? 0);
        $title = $data['title'] ?? $category?->name ?? 'Fee';

        $tenant = Tenant::query()->first();
        $fee = StudentFee::create([
            'tenant_id' => $tenant?->id,
            'id_card_id' => $card->id,
            'fee_category_id' => $category?->id,
            'title' => $title,
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $data['due_date'] ?? now()->addMonth(),
            'status' => 'pending',
            'academic_year' => $data['academic_year'] ?? $card->academic_year,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return ApiResponse::success($this->toRow($fee->load(['idCard', 'feeCategory'])), 'Fee assigned', 201);
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_card_ids' => ['required', 'array', 'min:1'],
            'id_card_ids.*' => ['integer', 'exists:id_cards,id'],
            'fee_category_id' => ['required', 'exists:fee_categories,id'],
            'due_date' => ['nullable', 'date'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $category = FeeCategory::query()->findOrFail($data['fee_category_id']);
        $tenant = Tenant::query()->first();
        $created = [];

        foreach ($data['id_card_ids'] as $idCardId) {
            $card = IdCard::query()->find($idCardId);
            if (! $card) {
                continue;
            }

            $fee = StudentFee::create([
                'tenant_id' => $tenant?->id,
                'id_card_id' => $card->id,
                'fee_category_id' => $category->id,
                'title' => $category->name,
                'amount' => $category->amount,
                'paid_amount' => 0,
                'due_date' => $data['due_date'] ?? now()->addMonth(),
                'status' => 'pending',
                'academic_year' => $data['academic_year'] ?? $card->academic_year,
            ]);
            $created[] = $this->toRow($fee->load(['idCard', 'feeCategory']));
        }

        return ApiResponse::success($created, count($created).' fees assigned', 201);
    }

    public function update(Request $request, StudentFee $studentFee): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(StudentFee::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $studentFee->update($data);
        $this->syncStatus($studentFee);

        return ApiResponse::success($this->toRow($studentFee->fresh()->load(['idCard', 'feeCategory'])), 'Updated');
    }

    public function destroy(StudentFee $studentFee): JsonResponse
    {
        $studentFee->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    private function syncStatus(StudentFee $fee): void
    {
        if ($fee->status === 'waived') {
            return;
        }

        $paid = (float) $fee->paid_amount;
        $amount = (float) $fee->amount;

        if ($paid >= $amount && $amount > 0) {
            $fee->update(['status' => 'paid']);
        } elseif ($paid > 0) {
            $fee->update(['status' => 'partial']);
        } else {
            $fee->update(['status' => 'pending']);
        }
    }

    /** @return array<string, mixed> */
    private function toRow(StudentFee $fee): array
    {
        return [
            'id' => $fee->id,
            'id_card_id' => $fee->id_card_id,
            'student_name' => $fee->idCard?->full_name,
            'card_number' => $fee->idCard?->card_number,
            'fee_category_id' => $fee->fee_category_id,
            'fee_category' => $fee->feeCategory?->name,
            'title' => $fee->title,
            'amount' => (float) $fee->amount,
            'paid_amount' => (float) $fee->paid_amount,
            'balance' => $fee->balance(),
            'due_date' => $fee->due_date?->format('Y-m-d'),
            'status' => $fee->status,
            'academic_year' => $fee->academic_year,
            'remarks' => $fee->remarks,
        ];
    }
}
