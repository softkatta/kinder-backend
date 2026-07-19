<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CertificateVerificationController;
use App\Http\Controllers\Api\V1\CmsItemController;
use App\Http\Controllers\Api\V1\ContactInquiryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\FileUploadController;
use App\Http\Controllers\Api\V1\FeeCategoryController;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Controllers\Api\V1\HomeworkController;
use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\InstallController;
use App\Http\Controllers\Api\V1\LicenseController;
use App\Http\Controllers\Api\V1\LiveStreamController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\PublicContentController;
use App\Http\Controllers\Api\V1\RazorpayWebhookController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StudentFeeController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TemplateDesigner\TemplateCategoryController;
use App\Http\Controllers\Api\V1\TemplateDesigner\TemplateController;
use App\Http\Controllers\Api\V1\TemplateDesigner\TemplateVariableController;
use App\Http\Controllers\Api\V1\TransportRouteController;
use App\Http\Controllers\Api\V1\UserController;
use App\Models\IdCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::bind('student', function (string $value) {
        return IdCard::query()
            ->where('card_type', 'student')
            ->findOrFail($value);
    });

    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'API is running',
            'data' => ['status' => 'ok'],
        ]);
    });

    // Install wizard (locked after successful installation)
    Route::prefix('install')->middleware(['throttle:30,1', 'install.not_completed'])->group(function () {
        Route::get('status', [InstallController::class, 'status']);
        Route::get('requirements', [InstallController::class, 'requirements']);
        Route::post('database', [InstallController::class, 'database']);
        Route::post('company-api', [InstallController::class, 'companyApi']);
        Route::post('admin', [InstallController::class, 'admin']);
        Route::post('activate', [InstallController::class, 'activate']);
        Route::get('configuration', [InstallController::class, 'downloadConfiguration']);
        Route::post('migrate', [InstallController::class, 'migrate']);
        Route::post('complete', [InstallController::class, 'complete']);
    });

    Route::get('license/entitlements', [LicenseController::class, 'entitlements']);
    Route::post('license/verify', [LicenseController::class, 'verify']);
    Route::post('license/activate', [InstallController::class, 'activate']);

    Route::post('/admissions', [AdmissionController::class, 'store']);

    Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);

    // Public website content (no auth)
    Route::prefix('public')->group(function () {
        Route::get('/homepage', [PublicContentController::class, 'homepage']);
        Route::get('/programs', [PublicContentController::class, 'programs']);
        Route::get('/facilities', [PublicContentController::class, 'facilities']);
        Route::get('/activities', [PublicContentController::class, 'activities']);
        Route::get('/events', [PublicContentController::class, 'events']);
        Route::get('/blog', [PublicContentController::class, 'blog']);
        Route::get('/gallery', [PublicContentController::class, 'gallery']);
        Route::get('/faqs', [PublicContentController::class, 'faqs']);
        Route::get('/jobs', [PublicContentController::class, 'jobs']);
        Route::get('/testimonials', [PublicContentController::class, 'testimonials']);
        Route::get('/staff', [PublicContentController::class, 'staff']);
        Route::get('/curriculum', [PublicContentController::class, 'curriculum']);
        Route::get('/notices', [PublicContentController::class, 'notices']);
        Route::get('/holidays', [PublicContentController::class, 'holidays']);
        Route::get('/school-profile', [PublicContentController::class, 'schoolProfile']);
        Route::get('/certificates/verify/{certNumber}', [CertificateVerificationController::class, 'show'])
            ->where('certNumber', 'CERT-[0-9]{4}-[0-9]+');
        Route::get('/broadcast-config', [SettingsController::class, 'broadcastConfig']);
        Route::get('/payment-info', [PublicContentController::class, 'paymentInfo']);
        Route::get('/content/{type}/{slug}', [PublicContentController::class, 'content']);
        Route::get('/pages/{slug}', [PublicContentController::class, 'page']);
        Route::post('/contact', [PublicContentController::class, 'contact']);
        Route::post('/jobs/apply', [PublicContentController::class, 'applyJob']);
        Route::post('/upload-admission-photo', [FileUploadController::class, 'uploadAdmissionPhoto']);
        Route::post('/upload-payment-proof', [FileUploadController::class, 'uploadPaymentProof']);
        Route::post('/payment-submit', [PaymentController::class, 'publicSubmit']);
        Route::patch('/admissions/{admission}/photo', [AdmissionController::class, 'attachPhoto']);
        Route::get('/live/active', [LiveStreamController::class, 'publicActive']);
        Route::get('/live/upcoming', [LiveStreamController::class, 'publicUpcoming']);
        Route::get('/live/{liveStream}/watch', [LiveStreamController::class, 'publicWatch']);
        Route::post('/live/{liveStream}/webrtc-token', [LiveStreamController::class, 'publicWebrtcToken']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard/admin', [DashboardController::class, 'admin'])
            ->middleware('role:super_admin');
        Route::get('/dashboard/cms-summary', [DashboardController::class, 'cmsSummary'])
            ->middleware('role:super_admin');
        Route::get('/dashboard/sidebar', [DashboardController::class, 'adminSidebar'])
            ->middleware('role:super_admin');
        Route::get('/dashboard/teacher', [DashboardController::class, 'teacher'])
            ->middleware('role:teacher,staff');
        Route::get('/dashboard/parent', [DashboardController::class, 'parent'])
            ->middleware('role:parent');
        Route::get('/dashboard/student', [DashboardController::class, 'student'])
            ->middleware('role:student');
        Route::get('/dashboard/guest', [DashboardController::class, 'guest'])
            ->middleware('role:guest');

        Route::get('/search', [SearchController::class, 'index']);

        Route::middleware('role:guest')->group(function () {
            Route::get('/guests/portal/profile', [GuestController::class, 'portalProfile']);
            Route::put('/guests/portal/companions', [GuestController::class, 'updatePortalCompanions']);
            Route::post('/files/guest', [FileUploadController::class, 'uploadGuest']);
        });

        // CMS CRUD (super admin)
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/files/cms', [FileUploadController::class, 'uploadCms']);
            Route::post('/files/upload', [FileUploadController::class, 'upload']);
            Route::post('/files/document', [FileUploadController::class, 'uploadDocument']);
            Route::post('/files/homework', [FileUploadController::class, 'uploadHomework']);
            Route::post('/files/teacher-photo', [FileUploadController::class, 'uploadTeacherPhoto']);
            Route::post('/files/template-background', [FileUploadController::class, 'uploadTemplateBackground']);
            Route::post('/files/template-asset', [FileUploadController::class, 'uploadTemplateAsset']);

            Route::get('/roles', [RoleController::class, 'index']);
            Route::get('/reports/attendance', [ReportController::class, 'attendance']);
            Route::get('/reports/students', [ReportController::class, 'students']);
            Route::get('/reports/payments', [ReportController::class, 'payments']);
            Route::get('/reports/admissions', [ReportController::class, 'admissions']);
            Route::get('/reports/fees', [ReportController::class, 'fees']);
            Route::get('/audit-logs', [AuditLogController::class, 'index']);

            Route::get('/students', [StudentController::class, 'index']);
            Route::post('/students', [StudentController::class, 'store']);
            Route::get('/students/{student}', [StudentController::class, 'show']);
            Route::put('/students/{student}', [StudentController::class, 'update']);
            Route::delete('/students/{student}', [StudentController::class, 'destroy']);
            Route::get('/students/{student}/documents', [StudentController::class, 'documents']);

            Route::get('/cms/items', [CmsItemController::class, 'index']);
            Route::post('/cms/items', [CmsItemController::class, 'store']);
            Route::get('/cms/items/{cmsItem}', [CmsItemController::class, 'show']);
            Route::put('/cms/items/{cmsItem}', [CmsItemController::class, 'update']);
            Route::delete('/cms/items/{cmsItem}', [CmsItemController::class, 'destroy']);
            Route::get('/cms/job-applications', [CmsItemController::class, 'jobApplications']);

            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);

            Route::get('/contact-inquiries', [ContactInquiryController::class, 'index']);
            Route::get('/contact-inquiries/{contactInquiry}', [ContactInquiryController::class, 'show']);
            Route::put('/contact-inquiries/{contactInquiry}', [ContactInquiryController::class, 'update']);
            Route::delete('/contact-inquiries/{contactInquiry}', [ContactInquiryController::class, 'destroy']);

            Route::get('/admissions', [AdmissionController::class, 'index']);
            Route::get('/admissions/{admission}', [AdmissionController::class, 'show']);
            Route::patch('/admissions/{admission}/approve', [AdmissionController::class, 'approve']);
            Route::patch('/admissions/{admission}/reject', [AdmissionController::class, 'reject']);

            // ID Cards
            Route::get('/id-cards', [IdCardController::class, 'index']);
            Route::post('/id-cards', [IdCardController::class, 'store']);
            Route::get('/id-cards/scan-history', [IdCardController::class, 'scanHistory']);
            Route::post('/id-cards/bulk-print', [IdCardController::class, 'bulkPrint']);
            Route::get('/id-cards/{idCard}', [IdCardController::class, 'show']);
            Route::put('/id-cards/{idCard}', [IdCardController::class, 'update']);
            Route::delete('/id-cards/{idCard}', [IdCardController::class, 'destroy']);
            Route::get('/id-cards/{idCard}/preview', [IdCardController::class, 'preview']);
            Route::get('/id-cards/{idCard}/print', [IdCardController::class, 'print']);

            // Academic years
            Route::get('/academic/years', [AcademicYearController::class, 'index']);
            Route::post('/academic/years', [AcademicYearController::class, 'store']);
            Route::get('/academic/years/{academicYear}', [AcademicYearController::class, 'show']);
            Route::put('/academic/years/{academicYear}', [AcademicYearController::class, 'update']);
            Route::delete('/academic/years/{academicYear}', [AcademicYearController::class, 'destroy']);

            // Exams & results
            Route::get('/exams', [ExamController::class, 'index']);
            Route::get('/exam-results', [ExamController::class, 'allResults']);
            Route::post('/exams', [ExamController::class, 'store']);
            Route::get('/exams/{exam}', [ExamController::class, 'show']);
            Route::put('/exams/{exam}', [ExamController::class, 'update']);
            Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
            Route::get('/exams/{exam}/results', [ExamController::class, 'results']);
            Route::post('/exams/{exam}/results', [ExamController::class, 'storeResult']);
            Route::put('/exam-results/{examResult}', [ExamController::class, 'updateResult']);
            Route::delete('/exam-results/{examResult}', [ExamController::class, 'destroyResult']);
            Route::get('/exam-results/{examResult}/marksheet', [ExamController::class, 'marksheetView']);
            Route::get('/exam-results/{examResult}/certificate', [ExamController::class, 'certificateView']);
            Route::post('/exam-results/{examResult}/printed', [ExamController::class, 'markPrinted']);

            Route::prefix('template-designer')->group(function () {
                Route::get('/categories', [TemplateCategoryController::class, 'index']);
                Route::get('/variables', [TemplateVariableController::class, 'index']);
                Route::get('/variables/sample', [TemplateVariableController::class, 'sample']);
                Route::get('/templates', [TemplateController::class, 'index']);
                Route::post('/templates', [TemplateController::class, 'store']);
                Route::get('/templates/{template}', [TemplateController::class, 'show']);
                Route::put('/templates/{template}', [TemplateController::class, 'update']);
                Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);
                Route::post('/templates/{template}/preview', [TemplateController::class, 'preview']);
                Route::post('/templates/{template}/generate', [TemplateController::class, 'generate']);
            });

            // Guest management
            Route::get('/guests', [GuestController::class, 'index']);
            Route::post('/guests', [GuestController::class, 'store']);
            Route::get('/guests/entry-logs', [GuestController::class, 'entryLogs']);
            Route::post('/guests/verify', [GuestController::class, 'verify']);
            Route::post('/guests/entry', [GuestController::class, 'entry']);
            Route::get('/guests/{guest}', [GuestController::class, 'show']);
            Route::put('/guests/{guest}', [GuestController::class, 'update']);
            Route::delete('/guests/{guest}', [GuestController::class, 'destroy']);

            // Payments
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);
            Route::patch('/payments/{payment}/verify', [PaymentController::class, 'verify']);
            Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
            Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
            Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
            Route::get('/payments/export', [PaymentController::class, 'export']);
            Route::get('/payments/outstanding', [PaymentController::class, 'outstanding']);
            Route::get('/payments/student/{student}/summary', [PaymentController::class, 'studentSummary']);
            Route::get('/payments/student/{student}/timeline', [PaymentController::class, 'studentTimeline']);
            Route::get('/payments/settings', [PaymentController::class, 'settings']);
            Route::put('/payments/settings', [PaymentController::class, 'updateSettings']);

            Route::get('/fee-categories', [FeeCategoryController::class, 'index']);
            Route::post('/fee-categories', [FeeCategoryController::class, 'store']);
            Route::put('/fee-categories/{feeCategory}', [FeeCategoryController::class, 'update']);
            Route::delete('/fee-categories/{feeCategory}', [FeeCategoryController::class, 'destroy']);

            Route::get('/student-fees', [StudentFeeController::class, 'index']);
            Route::post('/student-fees/assign', [StudentFeeController::class, 'assign']);
            Route::post('/student-fees/bulk-assign', [StudentFeeController::class, 'bulkAssign']);
            Route::put('/student-fees/{studentFee}', [StudentFeeController::class, 'update']);
            Route::delete('/student-fees/{studentFee}', [StudentFeeController::class, 'destroy']);

            Route::get('/transport-routes', [TransportRouteController::class, 'index']);
            Route::post('/transport-routes', [TransportRouteController::class, 'store']);
            Route::put('/transport-routes/{transportRoute}', [TransportRouteController::class, 'update']);
            Route::delete('/transport-routes/{transportRoute}', [TransportRouteController::class, 'destroy']);
            Route::patch('/students/{student}/transport', [TransportRouteController::class, 'assignStudent']);

            Route::get('/homework', [HomeworkController::class, 'index']);
            Route::post('/homework', [HomeworkController::class, 'store']);
            Route::get('/homework/{homework}', [HomeworkController::class, 'show']);
            Route::put('/homework/{homework}', [HomeworkController::class, 'update']);
            Route::delete('/homework/{homework}', [HomeworkController::class, 'destroy']);
            Route::get('/students/{student}/homework', [HomeworkController::class, 'studentList']);

            Route::get('/settings', [SettingsController::class, 'show']);
            Route::put('/settings', [SettingsController::class, 'update']);
            Route::post('/settings', [SettingsController::class, 'update']);
            // Alias path — Hostinger ModSecurity sometimes blocks /settings POST bodies.
            Route::get('/school-config', [SettingsController::class, 'show']);
            Route::post('/school-config', [SettingsController::class, 'update']);
            Route::post('/settings/test-integration', [SettingsController::class, 'testIntegration']);
            Route::post('/school-config/test-integration', [SettingsController::class, 'testIntegration']);
        });

        Route::middleware('role:parent')->prefix('portal/parent')->group(function () {
            Route::get('/children', [PortalController::class, 'parentChildren']);
            Route::get('/fees', [PortalController::class, 'parentFees']);
            Route::get('/attendance', [PortalController::class, 'parentAttendance']);
            Route::get('/notices', [PortalController::class, 'parentNotices']);
            Route::get('/payments/dashboard', [PaymentController::class, 'parentDashboard']);
            Route::get('/payments/razorpay/config', [PaymentController::class, 'razorpayConfig']);
            Route::post('/payments/razorpay/create-order', [PaymentController::class, 'createRazorpayOrder']);
            Route::post('/payments/razorpay/verify', [PaymentController::class, 'verifyRazorpay']);
        });

        Route::middleware('role:teacher,staff')->prefix('portal/teacher')->group(function () {
            Route::get('/students', [PortalController::class, 'teacherStudents']);
            Route::get('/notices', [PortalController::class, 'teacherNotices']);
            Route::get('/homework', [PortalController::class, 'teacherHomework']);
            Route::post('/homework', [PortalController::class, 'storeTeacherHomework']);
        });

        Route::middleware('role:student')->prefix('portal/student')->group(function () {
            Route::get('/attendance', [PortalController::class, 'studentAttendance']);
            Route::get('/homework', [PortalController::class, 'studentHomework']);
            Route::post('/homework/{homework}/submit', [HomeworkController::class, 'submit']);
            Route::get('/rewards', [PortalController::class, 'studentRewards']);
            Route::get('/activities', [PortalController::class, 'studentActivities']);
        });

        Route::middleware('role:teacher,staff,super_admin')->group(function () {
            Route::get('/homework/{homework}/submissions', [HomeworkController::class, 'submissions']);
            Route::patch('/homework-submissions/{homeworkSubmission}/review', [HomeworkController::class, 'reviewSubmission']);
        });

        Route::middleware('role:student,teacher,super_admin')->group(function () {
            Route::post('/files/homework', [FileUploadController::class, 'uploadHomework']);
            Route::post('/homework/{homework}/submit', [HomeworkController::class, 'submit']);
        });

        Route::middleware('role:super_admin,teacher,staff,parent,student,guest')->prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllRead']);
        });

        // Attendance QR + card verify (teacher + admin)
        Route::middleware('role:teacher,super_admin')->group(function () {
            Route::post('/attendance/qr-mark', [AttendanceController::class, 'qrMark']);
            Route::post('/scan/resolve', [AttendanceController::class, 'resolveCode']);
            Route::get('/attendance/daily', [AttendanceController::class, 'daily']);
            Route::get('/attendance/student/{student}/monthly', [AttendanceController::class, 'monthly']);
            Route::post('/id-cards/verify', [IdCardController::class, 'verify']);
        });

        // Teacher / staff — mobile camera publisher (register before admin live-stream wildcard routes)
        Route::middleware('role:teacher,staff')->group(function () {
            Route::get('/teacher/live-events', [LiveStreamController::class, 'publisherEvents']);
        });

        Route::middleware('role:teacher,staff')->prefix('live-streams')->group(function () {
            Route::get('/publisher/events', [LiveStreamController::class, 'publisherEvents']);
            Route::post('/{liveStream}/join-camera', [LiveStreamController::class, 'joinCamera']);
            Route::patch('/{liveStream}/cameras/{camera}/session', [LiveStreamController::class, 'updateCameraSession']);
            Route::post('/{liveStream}/cameras/{camera}/disconnect', [LiveStreamController::class, 'disconnectCamera']);
        });

        // Live stream management (admin only)
        Route::middleware('role:super_admin')->prefix('live-streams')->group(function () {
            Route::get('/cms-events', [LiveStreamController::class, 'cmsEvents']);
            Route::post('/from-cms/{cmsItem}', [LiveStreamController::class, 'linkFromCms']);
            Route::get('/', [LiveStreamController::class, 'index']);
            Route::post('/', [LiveStreamController::class, 'store']);
            Route::get('/{liveStream}', [LiveStreamController::class, 'show']);
            Route::put('/{liveStream}', [LiveStreamController::class, 'update']);
            Route::delete('/{liveStream}', [LiveStreamController::class, 'destroy']);

            Route::post('/{liveStream}/cameras', [LiveStreamController::class, 'storeCamera']);
            Route::put('/{liveStream}/cameras/{camera}', [LiveStreamController::class, 'updateCamera']);
            Route::delete('/{liveStream}/cameras/{camera}', [LiveStreamController::class, 'destroyCamera']);
            Route::patch('/{liveStream}/cameras/reorder', [LiveStreamController::class, 'reorderCameras']);
            Route::patch('/{liveStream}/active-camera', [LiveStreamController::class, 'setActiveCamera']);
            Route::get('/{liveStream}/cameras/{camera}/preview', [LiveStreamController::class, 'previewCamera']);
            Route::patch('/{liveStream}/cameras/{camera}/mute', [LiveStreamController::class, 'muteCamera']);
            Route::post('/{liveStream}/cameras/{camera}/disconnect', [LiveStreamController::class, 'disconnectCamera']);
            Route::get('/livekit/config', [LiveStreamController::class, 'livekitConfig']);

            Route::post('/{liveStream}/start', [LiveStreamController::class, 'start']);
            Route::post('/{liveStream}/pause', [LiveStreamController::class, 'pause']);
            Route::post('/{liveStream}/resume', [LiveStreamController::class, 'resume']);
            Route::post('/{liveStream}/stop', [LiveStreamController::class, 'stop']);
            Route::post('/{liveStream}/schedule', [LiveStreamController::class, 'schedule']);
            Route::post('/{liveStream}/cancel', [LiveStreamController::class, 'cancel']);
        });

        // Parent / student / guest — view-only live stream
        Route::middleware('role:parent,student,guest')->prefix('live-streams')->group(function () {
            Route::get('/active/viewer', [LiveStreamController::class, 'viewerActive']);
            Route::get('/upcoming/viewer', [LiveStreamController::class, 'viewerUpcoming']);
            Route::get('/{liveStream}/watch', [LiveStreamController::class, 'watch']);
        });

        // WebRTC tokens — staff publish; staff + viewers subscribe (authorization in controller)
        Route::middleware('role:super_admin,teacher,staff,parent,student,guest')
            ->post('/live-streams/{liveStream}/webrtc-token', [LiveStreamController::class, 'webrtcToken']);

        Route::post('/broadcasting/auth', function (Request $request) {
            return Broadcast::auth($request);
        });
    });

    // Signed playback redirect — no raw stream URLs in parent API responses
    Route::get('/live-streams/{liveStream}/playback', [LiveStreamController::class, 'playback'])
        ->name('live-stream.playback');
});
