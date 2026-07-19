<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactInquiry::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(fn (ContactInquiry $row) => $this->toRow($row));

        return ApiResponse::success($rows);
    }

    public function show(ContactInquiry $contactInquiry): JsonResponse
    {
        return ApiResponse::success($this->toRow($contactInquiry));
    }

    public function update(Request $request, ContactInquiry $contactInquiry): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'max:20'],
        ]);

        $contactInquiry->update($data);

        return ApiResponse::success($this->toRow($contactInquiry->fresh()), 'Updated');
    }

    public function destroy(ContactInquiry $contactInquiry): JsonResponse
    {
        $contactInquiry->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    /** @return array<string, mixed> */
    private function toRow(ContactInquiry $row): array
    {
        $status = match ($row->status) {
            'new', 'pending' => 'Pending',
            'review', 'reviewing' => 'Under Review',
            'approved', 'closed' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($row->status),
        };

        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'parent' => $row->email,
            'program' => $row->subject ?: 'General enquiry',
            'subject' => $row->subject,
            'message' => $row->message,
            'status' => $status,
            'status_raw' => $row->status,
            'date' => $row->created_at?->format('d M Y') ?? '',
            'date_raw' => $row->created_at?->toDateString(),
        ];
    }
}
