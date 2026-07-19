<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\IdCard;
use App\Services\IdCard\IdCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SoftKatta\Licensing\Services\LicenseService;

class StudentController extends Controller
{
    public function __construct(
        private readonly IdCardService $idCards,
        private readonly LicenseService $license,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = IdCard::query()->where('card_type', 'student')->with('transportRoute')->orderBy('full_name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('card_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success($query->get()->map(fn (IdCard $card) => $this->toRow($card)));
    }

    public function show(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        return ApiResponse::success($this->toRow($student));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->license->assertWithinLimit(
                'max_students',
                IdCard::query()->where('card_type', 'student')->count(),
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'photo_path' => ['nullable', 'string'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:30'],
            'meta' => ['nullable', 'array'],
        ]);

        $card = $this->idCards->create([
            'card_type' => 'student',
            'full_name' => $data['full_name'],
            'photo_path' => $data['photo_path'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'academic_year' => $data['academic_year'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);

        return ApiResponse::success($this->toRow($card), 'Student created', 201);
    }

    public function update(Request $request, IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:120'],
            'photo_path' => ['nullable', 'string'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'status' => ['sometimes', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:30'],
            'meta' => ['nullable', 'array'],
        ]);

        $student->update($data);

        return ApiResponse::success($this->toRow($student->fresh()), 'Student updated');
    }

    public function destroy(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $student->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function documents(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $meta = is_array($student->meta) ? $student->meta : [];

        return ApiResponse::success([
            'admission_number' => $meta['admission_number'] ?? $student->card_number,
            'photo_path' => $student->photo_path,
            'documents' => $meta['documents'] ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    private function toRow(IdCard $card): array
    {
        $meta = is_array($card->meta) ? $card->meta : [];

        return [
            'id' => $card->id,
            'full_name' => $card->full_name,
            'card_number' => $card->card_number,
            'status' => $card->status,
            'photo_path' => $card->photo_path,
            'blood_group' => $card->blood_group,
            'academic_year' => $card->academic_year,
            'emergency_contact' => $card->emergency_contact,
            'class' => $meta['class'] ?? null,
            'parent_name' => $meta['parent_name'] ?? null,
            'parent_email' => $meta['parent_email'] ?? null,
            'transport_route_id' => $card->transport_route_id,
            'transport_route' => $card->transportRoute?->only(['id', 'name', 'area', 'monthly_fee']),
            'meta' => $meta,
        ];
    }
}
