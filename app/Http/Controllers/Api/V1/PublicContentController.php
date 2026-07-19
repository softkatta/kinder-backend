<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CmsItem;
use App\Models\ContactInquiry;
use App\Models\JobApplication;
use App\Models\Tenant;
use App\Services\Notifications\SchoolNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicContentController extends Controller
{
    public function __construct(
        private readonly SchoolNotificationService $notifications,
    ) {}

    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', 'en');

        return in_array($locale, ['en', 'mr'], true) ? $locale : 'en';
    }

    public function programs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('program', $this->locale($request)));
    }

    public function facilities(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('facility', $this->locale($request)));
    }

    public function activities(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('activity', $this->locale($request)));
    }

    public function events(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('event', $this->locale($request)));
    }

    public function holidays(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        $items = CmsItem::query()
            ->where('type', 'event')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereJsonContains('meta->is_holiday', true)
                    ->orWhere('slug', 'like', '%holiday%');
            })
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CmsItem $item) => $item->toPublicArray($locale));

        return ApiResponse::success($items);
    }

    public function blog(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('blog', $this->locale($request)));
    }

    public function gallery(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('gallery', $this->locale($request)));
    }

    public function faqs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('faq', $this->locale($request)));
    }

    public function jobs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('job', $this->locale($request)));
    }

    public function testimonials(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('testimonial', $this->locale($request)));
    }

    public function staff(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('staff', $this->locale($request)));
    }

    public function curriculum(Request $request): JsonResponse
    {
        return ApiResponse::success($this->published('curriculum', $this->locale($request)));
    }

    public function homepage(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $profile = CmsItem::query()
            ->where('type', 'school_profile')
            ->where('status', 'published')
            ->first();

        return ApiResponse::success([
            'profile' => $profile?->toPublicArray($locale) ?? [
                'title' => 'Little Stars Kindergarten',
                'summary' => 'Nurturing young minds with joy and care.',
            ],
            'hero' => CmsItem::query()
                ->where('type', 'hero')
                ->where('status', 'published')
                ->first()?->toPublicArray($locale),
            'banners' => $this->published('banner', $locale),
            'programs' => $this->published('program', $locale),
            'facilities' => $this->published('facility', $locale),
            'activities' => $this->published('activity', $locale),
            'testimonials' => $this->published('testimonial', $locale),
            'events' => $this->published('event', $locale),
            'gallery' => $this->published('gallery', $locale),
            'staff' => $this->published('staff', $locale),
            'teachers' => $this->published('staff', $locale),
            'notices' => $this->publishedNotices($locale),
            'fee_plans' => $this->published('program', $locale),
        ]);
    }

    public function notices(Request $request): JsonResponse
    {
        return ApiResponse::success($this->publishedNotices($this->locale($request)));
    }

    public function schoolProfile(Request $request): JsonResponse
    {
        $profile = CmsItem::query()
            ->where('type', 'school_profile')
            ->where('status', 'published')
            ->first();

        return ApiResponse::success(array_merge($profile?->toPublicArray($this->locale($request)) ?? [
            'title' => 'Little Stars Kindergarten',
            'summary' => 'Nurturing young minds with joy and care.',
        ], [
            'logo_image' => $profile?->image,
            'favicon_image' => is_array($profile?->meta) ? ($profile->meta['favicon_image'] ?? null) : null,
            'short_name' => is_array($profile?->meta) ? ($profile->meta['short_name'] ?? null) : null,
        ]));
    }

    public function content(Request $request, string $type, string $slug): JsonResponse
    {
        $item = CmsItem::query()
            ->where('type', $type)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (! $item) {
            return ApiResponse::error('Content not found', 404);
        }

        return ApiResponse::success($item->toPublicArray($this->locale($request)));
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = CmsItem::query()
            ->where('type', 'page')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (! $page) {
            return ApiResponse::error('Page not found', 404);
        }

        return ApiResponse::success($page->toPublicArray($this->locale($request)));
    }

    public function paymentInfo(): JsonResponse
    {
        $settings = \App\Models\PaymentSetting::query()->first();

        if ($settings) {
            $qrUrl = $settings->upi_qr_path
                ? (str_starts_with($settings->upi_qr_path, 'http') ? $settings->upi_qr_path : url('/storage/'.ltrim($settings->upi_qr_path, '/')))
                : null;

            return ApiResponse::success([
                'upi_id' => $settings->upi_id,
                'account_name' => $settings->account_name,
                'account_number' => $settings->account_number,
                'ifsc_code' => $settings->ifsc_code,
                'bank_name' => $settings->bank_name,
                'branch' => $settings->branch,
                'upi_qr_url' => $qrUrl,
                'methods' => collect(['upi', 'cash', 'qr', 'razorpay'])
                    ->filter(fn ($m) => match ($m) {
                        'upi' => $settings->enable_upi,
                        'cash' => $settings->enable_cash,
                        'qr' => $settings->enable_qr,
                        'razorpay' => $settings->enable_razorpay,
                        default => false,
                    })->values()->all(),
                'note' => $settings->payment_note,
            ]);
        }

        $item = CmsItem::query()->where('type', 'payment_info')->where('status', 'published')->first();

        return ApiResponse::success($item?->meta ?? [
            'methods' => ['upi', 'cash'],
            'note' => 'Contact office for fee details.',
        ]);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:120',
            'phone' => 'nullable|string|max:20',
            'subject' => 'nullable|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        $tenant = Tenant::query()->first();

        ContactInquiry::create([
            ...$data,
            'tenant_id' => $tenant?->id,
        ]);

        $this->notifications->notifyAdmins(
            SchoolNotificationService::EVENT_CONTACT_INQUIRY,
            'New contact inquiry',
            sprintf("%s wrote: %s", $data['name'], \Illuminate\Support\Str::limit($data['message'], 200)),
            ['email' => $data['email'], 'phone' => $data['phone'] ?? null],
        );

        return ApiResponse::success(null, 'Thank you! We will get back to you soon.');
    }

    public function applyJob(Request $request): JsonResponse
    {
        $data = $request->validate([
            'job_id' => 'required|exists:cms_items,id',
            'full_name' => 'required|string|max:120',
            'email' => 'required|email|max:120',
            'phone' => 'required|string|max:20',
            'qualification' => 'nullable|string|max:200',
            'experience_years' => 'nullable|integer|min:0|max:50',
            'cover_letter' => 'nullable|string|max:5000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $job = CmsItem::query()->where('type', 'job')->findOrFail($data['job_id']);

        $resumePath = null;
        if ($request->hasFile('resume')) {
            $resumePath = $request->file('resume')->store('resumes', 'public');
        }

        JobApplication::create([
            'cms_item_id' => $job->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'qualification' => $data['qualification'] ?? null,
            'experience_years' => $data['experience_years'] ?? null,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_path' => $resumePath,
        ]);

        $this->notifications->notifyAdmins(
            SchoolNotificationService::EVENT_JOB_APPLICATION,
            'New job application',
            sprintf('%s applied for "%s".', $data['full_name'], $job->title),
            ['job_id' => $job->id, 'email' => $data['email']],
        );

        return ApiResponse::success(null, 'Application submitted successfully!');
    }

    private function published(string $type, string $locale = 'en'): array
    {
        return CmsItem::query()
            ->where('type', $type)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (CmsItem $item) => $item->toPublicArray($locale))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function publishedNotices(string $locale = 'en'): array
    {
        $today = now()->startOfDay();

        return CmsItem::query()
            ->where('type', 'notice')
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(function (CmsItem $item) use ($today) {
                $expires = $item->meta['expires_at'] ?? null;
                if (! is_string($expires) || $expires === '') {
                    return true;
                }

                try {
                    return \Carbon\Carbon::parse($expires)->startOfDay()->gte($today);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->map(fn (CmsItem $item) => $item->toPublicArray($locale))
            ->values()
            ->all();
    }
}
