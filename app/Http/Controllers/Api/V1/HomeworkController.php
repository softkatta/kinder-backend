<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\IdCard;
use App\Models\Tenant;
use App\Services\Portal\PortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeworkController extends Controller
{
    public function __construct(
        private readonly PortalService $portal,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Homework::query()->with('teacher:id,name')->latest('due_date');

        if ($class = $request->query('class_name')) {
            $query->where('class_name', $class);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success($query->get()->map(fn (Homework $hw) => $this->toRow($hw)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenant = Tenant::query()->first();

        $homework = Homework::create([
            ...$data,
            'tenant_id' => $tenant?->id,
            'teacher_user_id' => $request->user()->id,
        ]);

        return ApiResponse::success($this->toRow($homework->load('teacher')), 'Homework created', 201);
    }

    public function show(Homework $homework): JsonResponse
    {
        return ApiResponse::success($this->toRow($homework->load(['teacher', 'submissions.idCard'])));
    }

    public function update(Request $request, Homework $homework): JsonResponse
    {
        $homework->update($this->validated($request));

        return ApiResponse::success($this->toRow($homework->fresh()->load('teacher')), 'Updated');
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $homework->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function studentList(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $meta = is_array($student->meta) ? $student->meta : [];
        $className = $meta['class_name'] ?? $meta['class'] ?? null;

        $items = Homework::query()
            ->where('status', 'active')
            ->when($className, fn ($q) => $q->where(function ($q2) use ($className) {
                $q2->where('class_name', $className)->orWhereNull('class_name');
            }))
            ->latest('due_date')
            ->get();

        $submissions = HomeworkSubmission::query()
            ->where('id_card_id', $student->id)
            ->whereIn('homework_id', $items->pluck('id'))
            ->get()
            ->keyBy('homework_id');

        return ApiResponse::success($items->map(function (Homework $hw) use ($submissions) {
            $sub = $submissions->get($hw->id);

            return [
                ...$this->toRow($hw),
                'submission_status' => $sub?->status,
                'submitted_at' => $sub?->submitted_at?->toIso8601String(),
            ];
        }));
    }

    public function submissions(Homework $homework): JsonResponse
    {
        $rows = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->with(['idCard:id,full_name,card_number', 'submittedBy:id,name'])
            ->latest('submitted_at')
            ->get()
            ->map(fn (HomeworkSubmission $sub) => [
                'id' => $sub->id,
                'homework_id' => $sub->homework_id,
                'student_name' => $sub->idCard?->full_name,
                'card_number' => $sub->idCard?->card_number,
                'notes' => $sub->notes,
                'attachment_path' => $sub->attachment_path,
                'status' => $sub->status,
                'submitted_at' => $sub->submitted_at?->format('d M Y H:i'),
                'submitted_by' => $sub->submittedBy?->name,
            ]);

        return ApiResponse::success($rows);
    }

    public function reviewSubmission(Request $request, HomeworkSubmission $homeworkSubmission): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['submitted', 'reviewed', 'returned'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $homeworkSubmission->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $homeworkSubmission->notes,
        ]);

        return ApiResponse::success([
            'id' => $homeworkSubmission->id,
            'status' => $homeworkSubmission->status,
        ], 'Submission updated');
    }

    public function submit(Request $request, Homework $homework): JsonResponse
    {
        $user = $request->user();
        $card = $this->portal->studentCard($user);

        if (! $card) {
            return ApiResponse::error('Student ID card not linked', 422);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
        ]);

        $submission = HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'id_card_id' => $card->id],
            [
                'submitted_by_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ],
        );

        return ApiResponse::success([
            'id' => $submission->id,
            'homework_id' => $homework->id,
            'status' => $submission->status,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
        ], 'Homework submitted', 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'class_name' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'emoji' => ['nullable', 'string', 'max:8'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /** @return array<string, mixed> */
    private function toRow(Homework $hw): array
    {
        return [
            'id' => $hw->id,
            'title' => $hw->title,
            'body' => $hw->body,
            'class_name' => $hw->class_name,
            'due_date' => $hw->due_date?->format('Y-m-d'),
            'due' => $hw->due_date?->format('d M Y') ?? '—',
            'emoji' => $hw->emoji,
            'status' => $hw->status,
            'teacher_name' => $hw->teacher?->name,
            'submissions_count' => $hw->relationLoaded('submissions') ? $hw->submissions->count() : $hw->submissions()->count(),
        ];
    }
}
