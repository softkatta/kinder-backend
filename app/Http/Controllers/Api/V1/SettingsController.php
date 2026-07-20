<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\IntegrationTestBroadcast;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CmsItem;
use App\Models\PaymentSetting;
use App\Models\Tenant;
use App\Services\Notifications\IntegrationSettingsService;
use App\Services\Notifications\SchoolNotificationService;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    /** @var list<string> */
    private const PROFILE_IMAGE_KEYS = [
        'home_about_image',
        'home_why_image',
        'page_about_image',
        'about_page_image',
        'about_page_image_accent',
        'page_programs_image',
        'page_facilities_image',
        'page_activities_image',
        'page_events_image',
        'page_blog_image',
        'page_gallery_image',
        'page_staff_image',
        'page_curriculum_image',
        'page_careers_image',
        'page_contact_image',
        'page_faq_image',
        'page_admission_image',
        'page_book_tour_image',
        'page_payment_image',
        'page_live_image',
        'page_legal_image',
    ];

    public function __construct(
        private readonly IntegrationSettingsService $integrations,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success([
            'profile' => $this->profilePayload(),
            'notifications' => $this->notificationsPayload(),
            'payments' => $this->paymentsPayload(),
            'integrations' => $this->integrations->toApiPayload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        // Hostinger hcdn/ModSecurity often 403s JSON with nested profile/password/HTML.
        // SPA may send { "d": "<base64 json>" } instead of the raw settings object.
        $this->expandOpaquePayload($request);

        if ($request->has('profile')) {
            $this->updateProfile($request->validate([
                'profile' => ['required', 'array'],
                'profile.name' => ['sometimes', 'string', 'max:255'],
                'profile.short_name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'profile.phone' => ['sometimes', 'nullable', 'string', 'max:30'],
                'profile.address' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.city' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.hours' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.facebook_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.instagram_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.youtube_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.twitter_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.linkedin_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.map_embed_url' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'profile.latitude' => ['sometimes', 'nullable', 'string', 'max:40'],
                'profile.longitude' => ['sometimes', 'nullable', 'string', 'max:40'],
                'profile.meta_title' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
                'profile.meta_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.home_about_label' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.home_about_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.home_about_paragraphs' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'profile.home_about_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.home_why_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_about_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.about_page_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.about_page_image_accent' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_programs_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_facilities_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_activities_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_events_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_blog_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_gallery_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_staff_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_curriculum_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_careers_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_contact_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_faq_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_admission_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_book_tour_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_payment_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_live_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.page_legal_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.home_why_label' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.home_why_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.home_why_panel_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.home_why_panel_desc' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'profile.home_why_choose' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'profile.home_learning_label' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.home_learning_title_accent' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.home_learning_title_rest' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.home_learning_paragraphs' => ['sometimes', 'nullable', 'string', 'max:3000'],
                'profile.home_learning_items' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'profile.home_enroll_steps' => ['sometimes', 'nullable', 'string', 'max:3000'],
                'profile.home_cta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.home_cta_subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.established_year' => ['sometimes', 'nullable', 'string', 'max:20'],
                'profile.principal_name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.principal_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'profile.principal_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.vision' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'profile.mission' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'profile.about_values_label' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.about_values_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.about_values' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'profile.about_journey_label' => ['sometimes', 'nullable', 'string', 'max:120'],
                'profile.about_journey_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'profile.about_timeline' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'profile.logo_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.favicon_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'profile.cover_image' => ['sometimes', 'nullable', 'string', 'max:500'],
            ])['profile']);
        }

        if ($request->has('notifications')) {
            $this->updateNotifications($request->validate([
                'notifications' => ['required', 'array'],
                'notifications.*.event' => ['required', 'string', 'max:255'],
                'notifications.*.enabled' => ['required', 'boolean'],
                'notifications.*.channels' => ['sometimes', 'array'],
                'notifications.*.channels.email' => ['sometimes', 'boolean'],
                'notifications.*.channels.whatsapp' => ['sometimes', 'boolean'],
                'notifications.*.channels.push' => ['sometimes', 'boolean'],
            ])['notifications']);
        }

        if ($request->has('payments')) {
            $this->updatePayments($request->validate([
                'payments' => ['required', 'array'],
                'payments.upi_id' => ['nullable', 'string', 'max:120'],
                'payments.account_name' => ['nullable', 'string', 'max:120'],
                'payments.account_number' => ['nullable', 'string', 'max:40'],
                'payments.ifsc_code' => ['nullable', 'string', 'max:20'],
                'payments.bank_name' => ['nullable', 'string', 'max:120'],
                'payments.branch' => ['nullable', 'string', 'max:120'],
                'payments.upi_qr_path' => ['nullable', 'string'],
                'payments.enable_upi' => ['sometimes', 'boolean'],
                'payments.enable_cash' => ['sometimes', 'boolean'],
                'payments.enable_qr' => ['sometimes', 'boolean'],
                'payments.enable_razorpay' => ['sometimes', 'boolean'],
                'payments.razorpay_key_id' => ['nullable', 'string', 'max:120'],
                'payments.razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
                'payments.payment_note' => ['nullable', 'string'],
                'payments.enable_online_payments' => ['sometimes', 'boolean'],
                'payments.enable_upi_manual' => ['sometimes', 'boolean'],
            ])['payments']);
        }

        if ($request->has('integrations')) {
            $this->updateIntegrations($request->validate([
                'integrations' => ['required', 'array'],
                'integrations.email' => ['sometimes', 'array'],
                'integrations.email.enabled' => ['sometimes', 'boolean'],
                'integrations.email.mailer' => ['nullable', 'string', 'max:40'],
                'integrations.email.host' => ['nullable', 'string', 'max:255'],
                'integrations.email.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'integrations.email.username' => ['nullable', 'string', 'max:255'],
                'integrations.email.password' => ['nullable', 'string', 'max:255'],
                'integrations.email.encryption' => ['nullable', 'string', 'max:10'],
                'integrations.email.from_address' => ['nullable', 'email', 'max:255'],
                'integrations.email.from_name' => ['nullable', 'string', 'max:120'],
                'integrations.whatsapp' => ['sometimes', 'array'],
                'integrations.whatsapp.enabled' => ['sometimes', 'boolean'],
                'integrations.whatsapp.provider' => ['nullable', 'in:twilio,meta'],
                'integrations.whatsapp.account_sid' => ['nullable', 'string', 'max:120'],
                'integrations.whatsapp.auth_token' => ['nullable', 'string', 'max:255'],
                'integrations.whatsapp.from_number' => ['nullable', 'string', 'max:40'],
                'integrations.whatsapp.phone_number_id' => ['nullable', 'string', 'max:80'],
                'integrations.whatsapp.access_token' => ['nullable', 'string', 'max:500'],
                'integrations.broadcast' => ['sometimes', 'array'],
                'integrations.broadcast.enabled' => ['sometimes', 'boolean'],
                'integrations.broadcast.driver' => ['nullable', 'in:reverb,pusher,log'],
                'integrations.broadcast.app_id' => ['nullable', 'string', 'max:80'],
                'integrations.broadcast.key' => ['nullable', 'string', 'max:120'],
                'integrations.broadcast.secret' => ['nullable', 'string', 'max:255'],
                'integrations.broadcast.cluster' => ['nullable', 'string', 'max:40'],
                'integrations.broadcast.host' => ['nullable', 'string', 'max:255'],
                'integrations.broadcast.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'integrations.broadcast.scheme' => ['nullable', 'in:http,https'],
            ])['integrations']);
        }

        return ApiResponse::success([
            'profile' => $this->profilePayload(),
            'notifications' => $this->notificationsPayload(),
            'payments' => $this->paymentsPayload(),
            'integrations' => $this->integrations->toApiPayload(),
        ], 'Settings saved');
    }

    public function testIntegration(Request $request): JsonResponse
    {
        $this->expandOpaquePayload($request);

        $data = $request->validate([
            'type' => ['required', 'in:email,whatsapp,broadcast'],
            'to' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $settings = $this->integrations->get();

        try {
            return match ($data['type']) {
                'email' => $this->testEmail($data['to'] ?? $user?->email, $settings),
                'whatsapp' => $this->testWhatsapp($data['to'] ?? $user?->phone, $settings),
                'broadcast' => $this->testBroadcast($user?->id, $settings),
            };
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function broadcastConfig(): JsonResponse
    {
        return ApiResponse::success($this->integrations->publicBroadcastConfig());
    }

    private function testEmail(?string $to, $settings): JsonResponse
    {
        if (! $to) {
            return ApiResponse::error('Recipient email is required.', 422);
        }
        if (! $settings->email_enabled) {
            return ApiResponse::error('Email integration is disabled.', 422);
        }

        $this->integrations->applyMailConfig($settings);
        Mail::raw('This is a test email from Kindergarten ERP notification settings.', function ($message) use ($to, $settings) {
            $message->to($to)
                ->subject('Test Email — Kindergarten ERP')
                ->from(
                    $settings->email_from_address ?: config('mail.from.address'),
                    $settings->email_from_name ?: config('mail.from.name'),
                );
        });

        return ApiResponse::success(null, 'Test email sent to '.$to);
    }

    private function testWhatsapp(?string $to, $settings): JsonResponse
    {
        if (! $to) {
            return ApiResponse::error('Recipient phone number is required.', 422);
        }
        if (! $settings->whatsapp_enabled) {
            return ApiResponse::error('WhatsApp integration is disabled.', 422);
        }

        $this->whatsapp->send($to, 'Test message from Kindergarten ERP — WhatsApp integration is working.', $settings);

        return ApiResponse::success(null, 'Test WhatsApp message sent.');
    }

    private function testBroadcast(?int $userId, $settings): JsonResponse
    {
        if (! $userId) {
            return ApiResponse::error('Authentication required.', 401);
        }
        if (! $settings->broadcast_enabled) {
            return ApiResponse::error('Realtime broadcast is disabled.', 422);
        }

        $this->integrations->applyBroadcastConfig($settings);
        broadcast(new IntegrationTestBroadcast($userId));

        return ApiResponse::success(null, 'Test broadcast event sent. Check admin realtime connection.');
    }

    /** @param array<string, mixed> $payload */
    private function updateIntegrations(array $payload): void
    {
        $settings = $this->integrations->get();
        if (isset($payload['email'])) {
            $this->integrations->updateEmail($payload['email'], $settings);
            $settings->refresh();
        }
        if (isset($payload['whatsapp'])) {
            $this->integrations->updateWhatsapp($payload['whatsapp'], $settings);
            $settings->refresh();
        }
        if (isset($payload['broadcast'])) {
            $this->integrations->updateBroadcast($payload['broadcast'], $settings);
        }
    }

    /** @return array<string, mixed> */
    private function profilePayload(): array
    {
        $tenant = Tenant::query()->first();
        $profile = $this->schoolProfileItem();
        $meta = is_array($profile?->meta) ? $profile->meta : [];

        return [
            'name' => $profile?->title ?? $tenant?->name ?? 'Little Stars Kindergarten',
            'short_name' => $meta['short_name'] ?? null,
            'email' => $meta['email'] ?? $tenant?->email,
            'phone' => $meta['phone'] ?? $tenant?->phone,
            'address' => $meta['address'] ?? '',
            'city' => $meta['city'] ?? '',
            'hours' => $meta['hours'] ?? '',
            'facebook_url' => $meta['facebook_url'] ?? '',
            'instagram_url' => $meta['instagram_url'] ?? '',
            'youtube_url' => $meta['youtube_url'] ?? '',
            'twitter_url' => $meta['twitter_url'] ?? '',
            'linkedin_url' => $meta['linkedin_url'] ?? '',
            'map_embed_url' => $meta['map_embed_url'] ?? '',
            'latitude' => $meta['latitude'] ?? '',
            'longitude' => $meta['longitude'] ?? '',
            'logo_image' => $profile?->image,
            'favicon_image' => $meta['favicon_image'] ?? null,
            'cover_image' => $meta['cover_image'] ?? null,
            'meta_title' => $meta['meta_title'] ?? null,
            'meta_description' => $meta['meta_description'] ?? ($profile?->summary),
            'meta_image' => $meta['meta_image'] ?? null,
            'home_about_label' => $meta['home_about_label'] ?? null,
            'home_about_title' => $meta['home_about_title'] ?? null,
            'home_about_paragraphs' => $meta['home_about_paragraphs'] ?? null,
            ...array_combine(
                self::PROFILE_IMAGE_KEYS,
                array_map(fn (string $key) => $meta[$key] ?? null, self::PROFILE_IMAGE_KEYS),
            ),
            'home_why_label' => $meta['home_why_label'] ?? null,
            'home_why_title' => $meta['home_why_title'] ?? null,
            'home_why_panel_title' => $meta['home_why_panel_title'] ?? null,
            'home_why_panel_desc' => $meta['home_why_panel_desc'] ?? null,
            'home_why_choose' => $meta['home_why_choose'] ?? null,
            'home_learning_label' => $meta['home_learning_label'] ?? null,
            'home_learning_title_accent' => $meta['home_learning_title_accent'] ?? null,
            'home_learning_title_rest' => $meta['home_learning_title_rest'] ?? null,
            'home_learning_paragraphs' => $meta['home_learning_paragraphs'] ?? null,
            'home_learning_items' => $meta['home_learning_items'] ?? null,
            'home_enroll_steps' => $meta['home_enroll_steps'] ?? null,
            'home_cta_title' => $meta['home_cta_title'] ?? null,
            'home_cta_subtitle' => $meta['home_cta_subtitle'] ?? null,
            'established_year' => $meta['established_year'] ?? null,
            'principal_name' => $meta['principal_name'] ?? null,
            'principal_message' => $meta['principal_message'] ?? null,
            'principal_image' => $meta['principal_image'] ?? null,
            'vision' => $meta['vision'] ?? null,
            'mission' => $meta['mission'] ?? null,
            'about_values_label' => $meta['about_values_label'] ?? null,
            'about_values_title' => $meta['about_values_title'] ?? null,
            'about_values' => $meta['about_values'] ?? null,
            'about_journey_label' => $meta['about_journey_label'] ?? null,
            'about_journey_title' => $meta['about_journey_title'] ?? null,
            'about_timeline' => $meta['about_timeline'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function notificationsPayload(): array
    {
        $profile = $this->schoolProfileItem();
        $meta = is_array($profile?->meta) ? $profile->meta : [];
        $saved = $meta['notifications'] ?? null;

        if (is_array($saved) && $saved !== []) {
            return array_values($saved);
        }

        return $this->defaultNotifications();
    }

    /** @return list<array<string, mixed>> */
    private function defaultNotifications(): array
    {
        return [
            ['key' => SchoolNotificationService::EVENT_NEW_ADMISSION, 'event' => 'New admission applications', 'channel' => 'Email + Push', 'enabled' => true, 'desc' => 'When a parent submits admission form', 'channels' => ['email' => true, 'whatsapp' => false, 'push' => true]],
            ['key' => SchoolNotificationService::EVENT_FEE_PAYMENT, 'event' => 'Fee payment received', 'channel' => 'Email + WhatsApp + Push', 'enabled' => true, 'desc' => 'On successful online payments', 'channels' => ['email' => true, 'whatsapp' => true, 'push' => true]],
            ['key' => SchoolNotificationService::EVENT_ATTENDANCE_SUMMARY, 'event' => 'Daily attendance summary', 'channel' => 'Email + Push', 'enabled' => true, 'desc' => 'End-of-day report for all classes', 'channels' => ['email' => true, 'whatsapp' => false, 'push' => true]],
            ['key' => SchoolNotificationService::EVENT_JOB_APPLICATION, 'event' => 'Job applications', 'channel' => 'Email + Push', 'enabled' => true, 'desc' => 'When someone applies via careers page', 'channels' => ['email' => true, 'whatsapp' => false, 'push' => true]],
            ['key' => SchoolNotificationService::EVENT_CONTACT_INQUIRY, 'event' => 'Contact form inquiries', 'channel' => 'Email + Push', 'enabled' => true, 'desc' => 'When someone submits the website contact form', 'channels' => ['email' => true, 'whatsapp' => false, 'push' => true]],
        ];
    }

    /** @return array<string, mixed> */
    private function paymentsPayload(): array
    {
        $settings = PaymentSetting::query()->first();

        return [
            'enable_razorpay' => $settings?->enable_razorpay ?? false,
            'razorpay_key_id' => $settings?->razorpay_key_id,
            'razorpay_webhook_secret' => null,
            'enable_online_payments' => $settings?->enable_razorpay ?? false,
            'enable_cash' => $settings?->enable_cash ?? true,
            'enable_upi_manual' => $settings?->enable_upi ?? true,
            'enable_qr' => $settings?->enable_qr ?? true,
        ];
    }

    /** @param array<string, mixed> $data */
    private function updateProfile(array $data): void
    {
        $tenant = Tenant::query()->first();
        if ($tenant) {
            $tenant->update(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($v) => $v !== null));
        }

        $profile = $this->schoolProfileItem();
        $meta = is_array($profile?->meta) ? $profile->meta : [];

        if (isset($data['email'])) {
            $meta['email'] = $data['email'];
        }
        if (isset($data['phone'])) {
            $meta['phone'] = $data['phone'];
        }
        if (array_key_exists('address', $data)) {
            $meta['address'] = $data['address'];
        }
        if (array_key_exists('city', $data)) {
            $meta['city'] = $data['city'];
        }
        if (array_key_exists('hours', $data)) {
            $meta['hours'] = $data['hours'];
        }
        if (array_key_exists('short_name', $data)) {
            $meta['short_name'] = $data['short_name'];
        }
        foreach ([
            'facebook_url', 'instagram_url', 'youtube_url', 'twitter_url', 'linkedin_url',
            'map_embed_url', 'latitude', 'longitude',
            'meta_title', 'meta_description', 'meta_image',
            'home_about_label', 'home_about_title', 'home_about_paragraphs',
            ...self::PROFILE_IMAGE_KEYS,
            'home_why_label', 'home_why_title', 'home_why_panel_title', 'home_why_panel_desc', 'home_why_choose',
            'home_learning_label', 'home_learning_title_accent', 'home_learning_title_rest',
            'home_learning_paragraphs', 'home_learning_items', 'home_enroll_steps',
            'home_cta_title', 'home_cta_subtitle',
            'established_year', 'principal_name', 'principal_message', 'principal_image', 'vision', 'mission',
            'about_values_label', 'about_values_title', 'about_values',
            'about_journey_label', 'about_journey_title', 'about_timeline',
            'favicon_image',
        ] as $homeField) {
            if (array_key_exists($homeField, $data)) {
                $meta[$homeField] = $data[$homeField];
            }
        }
        if (array_key_exists('cover_image', $data)) {
            $meta['cover_image'] = $data['cover_image'];
        }

        $payload = [
            'tenant_id' => $tenant?->id,
            'type' => 'school_profile',
            'slug' => 'profile',
            'status' => 'published',
            'meta' => $meta,
        ];

        if (isset($data['name'])) {
            $payload['title'] = $data['name'];
            $meta['school_name'] = $data['name'];
            $payload['meta'] = $meta;
        }
        if (array_key_exists('logo_image', $data)) {
            $payload['image'] = $data['logo_image'];
        }

        if ($profile) {
            $profile->update($payload);
        } else {
            CmsItem::create([
                ...$payload,
                'title' => $data['name'] ?? 'Little Stars Kindergarten',
                'summary' => 'Nurturing young minds with joy and care.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $notifications */
    private function updateNotifications(array $notifications): void
    {
        $profile = $this->schoolProfileItem();
        $meta = is_array($profile?->meta) ? $profile->meta : [];
        $defaults = collect($this->defaultNotifications())->keyBy('event');

        $merged = collect($notifications)->map(function (array $row) use ($defaults) {
            $base = $defaults->get($row['event'], []);

            return [
                'key' => $base['key'] ?? strtolower(str_replace(' ', '_', $row['event'])),
                'event' => $row['event'],
                'channel' => $base['channel'] ?? 'Email',
                'desc' => $base['desc'] ?? '',
                'enabled' => (bool) $row['enabled'],
                'channels' => [
                    'email' => (bool) ($row['channels']['email'] ?? $base['channels']['email'] ?? true),
                    'whatsapp' => (bool) ($row['channels']['whatsapp'] ?? $base['channels']['whatsapp'] ?? false),
                    'push' => (bool) ($row['channels']['push'] ?? $base['channels']['push'] ?? true),
                ],
            ];
        })->values()->all();

        $meta['notifications'] = $merged;

        if ($profile) {
            $profile->update(['meta' => $meta]);
        } else {
            $tenant = Tenant::query()->first();
            CmsItem::create([
                'tenant_id' => $tenant?->id,
                'type' => 'school_profile',
                'slug' => 'profile',
                'title' => $tenant?->name ?? 'Little Stars Kindergarten',
                'summary' => 'Nurturing young minds with joy and care.',
                'status' => 'published',
                'meta' => $meta,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function updatePayments(array $data): void
    {
        $tenant = Tenant::query()->first();
        $settings = PaymentSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant?->id],
            ['enable_upi' => true, 'enable_cash' => true, 'enable_qr' => true],
        );

        $update = [];

        if (array_key_exists('razorpay_key_id', $data)) {
            $update['razorpay_key_id'] = $data['razorpay_key_id'];
        }
        if (array_key_exists('razorpay_webhook_secret', $data) && $data['razorpay_webhook_secret']) {
            $update['razorpay_webhook_secret'] = $data['razorpay_webhook_secret'];
        }
        if (array_key_exists('enable_razorpay', $data)) {
            $update['enable_razorpay'] = (bool) $data['enable_razorpay'];
        }
        if (array_key_exists('enable_online_payments', $data)) {
            $update['enable_razorpay'] = (bool) $data['enable_online_payments'];
        }
        if (array_key_exists('enable_cash', $data)) {
            $update['enable_cash'] = (bool) $data['enable_cash'];
        }
        if (array_key_exists('enable_upi_manual', $data)) {
            $update['enable_upi'] = (bool) $data['enable_upi_manual'];
        }
        if (array_key_exists('enable_qr', $data)) {
            $update['enable_qr'] = (bool) $data['enable_qr'];
        }

        $settings->update($update);
    }

    /**
     * Decode SPA opaque payload used to avoid Hostinger hcdn false-positive 403s.
     * Expected shape: { "d": "<base64-encoded JSON object>" }.
     */
    private function expandOpaquePayload(Request $request): void
    {
        $encoded = $request->input('d');
        if (! is_string($encoded) || $encoded === '') {
            return;
        }

        $json = base64_decode($encoded, true);
        if ($json === false || $json === '') {
            return;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return;
        }

        $request->merge($data);
    }

    private function schoolProfileItem(): ?CmsItem
    {
        return CmsItem::query()
            ->where('type', 'school_profile')
            ->first();
    }
}
