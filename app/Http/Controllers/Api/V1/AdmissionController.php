<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Admission;
use App\Models\Tenant;
use App\Services\Admission\AdmissionOnboardingService;
use App\Services\Audit\AuditLogService;
use App\Services\Notifications\SchoolNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdmissionController extends Controller
{
    public function __construct(
        private readonly SchoolNotificationService $notifications,
        private readonly AdmissionOnboardingService $onboarding,
        private readonly AuditLogService $audit,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:120'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'grade_level' => ['nullable', 'string', 'max:20'],
            'parent_info' => ['nullable', 'array'],
            'parent_info.full_name' => ['nullable', 'string', 'max:120'],
            'parent_info.phone' => ['nullable', 'string', 'max:30'],
            'parent_info.email' => ['nullable', 'email', 'max:255'],
            'address_info' => ['nullable', 'array'],
            'address_info.address' => ['nullable', 'string', 'max:500'],
            'photo_path' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant = Tenant::query()->first();
        $parent = is_array($data['parent_info'] ?? null) ? $data['parent_info'] : [];

        $admission = Admission::create([
            'tenant_id' => $tenant?->id,
            'applicant_name' => $data['applicant_name'],
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'grade_level' => $data['grade_level'] ?? null,
            'parent_info' => $parent,
            'address_info' => $data['address_info'] ?? null,
            'photo_path' => $data['photo_path'] ?? null,
            'status' => 'pending',
        ]);

        $this->notifications->notifyAdmins(
            SchoolNotificationService::EVENT_NEW_ADMISSION,
            'New admission application',
            sprintf(
                '%s applied for %s. Parent: %s (%s).',
                $admission->applicant_name,
                strtoupper((string) ($admission->grade_level ?: 'grade not set')),
                $parent['full_name'] ?? '—',
                $parent['phone'] ?? '—',
            ),
            ['admission_id' => $admission->id],
        );

        return ApiResponse::success($this->toRow($admission), 'Application submitted', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Admission::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success($query->get()->map(fn (Admission $row) => $this->toRow($row)));
    }

    public function show(Admission $admission): JsonResponse
    {
        return ApiResponse::success($this->toRow($admission));
    }

    public function approve(Request $request, Admission $admission): JsonResponse
    {
        $admission->update([
            'status' => 'approved',
            'remarks' => $request->input('remarks', $admission->remarks),
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $provisioned = $this->onboarding->provisionFromAdmission($admission->fresh());

        $this->audit->log(
            $request->user(),
            'admission.approved',
            "Approved admission for {$admission->applicant_name}",
            'admission',
            $admission->id,
            $provisioned,
            $request,
        );

        $row = $this->toRow($admission->fresh());
        $row['provisioned'] = $provisioned;

        $message = 'Application approved';
        if ($provisioned['parent_created'] && $provisioned['temp_password'] && config('app.debug')) {
            $message .= '. Parent temp password (dev): '.$provisioned['temp_password'];
        }

        return ApiResponse::success($row, $message);
    }

    public function reject(Request $request, Admission $admission): JsonResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $admission->update([
            'status' => 'rejected',
            'remarks' => $data['remarks'] ?? $admission->remarks,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return ApiResponse::success($this->toRow($admission->fresh()), 'Application rejected');
    }

    public function attachPhoto(Request $request, Admission $admission): JsonResponse
    {
        $data = $request->validate([
            'photo_path' => ['required', 'string', 'max:500'],
        ]);

        $admission->update(['photo_path' => $data['photo_path']]);

        return ApiResponse::success($this->toRow($admission->fresh()), 'Photo saved');
    }

    /** @return array<string, mixed> */
    private function toRow(Admission $row): array
    {
        $parent = is_array($row->parent_info) ? $row->parent_info : [];

        return [
            'id' => $row->id,
            'applicant_name' => $row->applicant_name,
            'dob' => $row->dob?->format('Y-m-d'),
            'gender' => $row->gender,
            'grade_level' => $row->grade_level,
            'parent_name' => $parent['full_name'] ?? null,
            'parent_phone' => $parent['phone'] ?? null,
            'parent_email' => $parent['email'] ?? null,
            'address' => is_array($row->address_info) ? ($row->address_info['address'] ?? null) : null,
            'parent_info' => $row->parent_info,
            'address_info' => $row->address_info,
            'photo_path' => $row->photo_path,
            'status' => $row->status,
            'status_label' => ucfirst($row->status),
            'remarks' => $row->remarks,
            'date' => $row->created_at?->format('d M Y'),
            'reviewed_at' => $row->reviewed_at?->format('d M Y, h:i A'),
            'student_id_card_id' => $row->student_id_card_id,
            'parent_user_id' => $row->parent_user_id,
        ];
    }
}
