<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Portal\PortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __construct(
        private readonly PortalService $portal,
    ) {}

    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', 'en');

        return in_array($locale, ['en', 'mr'], true) ? $locale : 'en';
    }

    public function parentChildren(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->parentChildren($request->user()));
    }

    public function parentFees(Request $request): JsonResponse
    {
        $childId = $request->query('child_id') ? (int) $request->query('child_id') : null;

        return ApiResponse::success($this->portal->parentFees($request->user(), $childId));
    }

    public function parentAttendance(Request $request): JsonResponse
    {
        $id = $request->query('child_id') ? (int) $request->query('child_id') : null;

        return ApiResponse::success($this->portal->parentAttendance($request->user(), $id));
    }

    public function parentNotices(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->portalNotices($this->locale($request)));
    }

    public function teacherStudents(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->teacherStudents($request->user()));
    }

    public function teacherNotices(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->portalNotices($this->locale($request)));
    }

    public function teacherHomework(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->portal->homeworkItems($this->locale($request), $request->user()),
        );
    }

    public function studentAttendance(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->studentAttendance($request->user()));
    }

    public function studentHomework(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->studentHomeworkItems($request->user()));
    }

    public function storeTeacherHomework(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due' => ['nullable', 'string', 'max:80'],
            'class_name' => ['nullable', 'string', 'max:80'],
            'emoji' => ['nullable', 'string', 'max:8'],
        ]);

        $item = $this->portal->createHomework($data, $request->user());

        return ApiResponse::success($item, 'Homework assigned', 201);
    }

    public function studentRewards(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->studentRewards($request->user()));
    }

    public function studentActivities(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->studentActivities($this->locale($request)));
    }
}
