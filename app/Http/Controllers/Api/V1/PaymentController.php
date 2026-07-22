<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Admission;
use App\Models\IdCard;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\StudentFee;
use App\Models\Tenant;
use App\Services\Audit\AuditLogService;
use App\Services\Fees\StudentFeePaymentService;
use App\Services\Notifications\SchoolNotificationService;
use App\Services\Portal\PortalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly SchoolNotificationService $notifications,
        private readonly PortalService $portal,
        private readonly AuditLogService $audit,
        private readonly StudentFeePaymentService $feePayments,
    ) {}
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($method = $request->query('payment_method')) {
            $query->where('payment_method', $method);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                    ->orWhere('payer_name', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success($query->get()->map(fn (Payment $p) => $this->toRow($p)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayment($request);
        $tenant = Tenant::query()->first();
        $status = $data['status'] ?? 'pending';
        if ($request->user() && ! isset($data['status'])) {
            $status = $data['payment_method'] === 'cash' ? 'verified' : 'pending';
        }

        $payment = Payment::create([
            ...$data,
            'tenant_id' => $tenant?->id,
            'status' => $status,
            'verified_at' => $status === 'verified' ? now() : null,
            'verified_by_user_id' => $status === 'verified' ? $request->user()?->id : null,
        ]);

        return ApiResponse::success($this->toRow($payment), 'Payment recorded', 201);
    }

    public function publicSubmit(Request $request): JsonResponse
    {
        if ($request->has('transaction_proof_path') && ! $request->has('proof_path')) {
            $request->merge(['proof_path' => $request->input('transaction_proof_path')]);
        }

        $method = $request->input('payment_method');
        if (in_array($method, ['gpay', 'phonepe', 'paytm', 'bank_transfer'], true)) {
            $request->merge(['payment_method' => 'upi']);
        }

        $data = $this->validatedPayment($request);
        $tenant = Tenant::query()->first();

        $payment = Payment::create([
            ...$data,
            'tenant_id' => $tenant?->id,
            'status' => 'pending',
        ]);

        return ApiResponse::success(['id' => $payment->id], 'Payment submitted for verification', 201);
    }

    public function verify(Request $request, Payment $payment): JsonResponse
    {
        $approved = $request->validate(['approved' => ['sometimes', 'boolean']])['approved'] ?? true;

        $payment->update([
            'status' => $approved ? 'verified' : 'rejected',
            'verified_at' => now(),
            'verified_by_user_id' => $request->user()?->id,
        ]);

        if ($approved) {
            $fresh = $payment->fresh();
            $this->feePayments->applyPayment($fresh);
            $this->notifications->notifyAdmins(
                SchoolNotificationService::EVENT_FEE_PAYMENT,
                'Fee payment received',
                sprintf(
                    'Payment of ₹%s received from %s (%s).',
                    number_format((float) $fresh->amount, 0),
                    $fresh->payer_name,
                    strtoupper($fresh->payment_method),
                ),
                ['payment_id' => $fresh->id],
            );
            $this->audit->log(
                $request->user(),
                'payment.verified',
                "Verified payment #{$payment->id} from {$fresh->payer_name}",
                'payment',
                $payment->id,
                null,
                $request,
            );
        }

        return ApiResponse::success($this->toRow($payment->fresh()), $approved ? 'Payment verified' : 'Payment rejected');
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success($this->settingsPayload());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upi_id' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'upi_qr_path' => ['nullable', 'string'],
            'enable_upi' => ['sometimes', 'boolean'],
            'enable_cash' => ['sometimes', 'boolean'],
            'enable_qr' => ['sometimes', 'boolean'],
            'enable_razorpay' => ['sometimes', 'boolean'],
            'razorpay_key_id' => ['nullable', 'string', 'max:120'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
            'payment_note' => ['nullable', 'string'],
        ]);

        $tenant = Tenant::query()->first();
        $settings = PaymentSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant?->id],
            ['enable_upi' => true, 'enable_cash' => true, 'enable_qr' => true],
        );
        $settings->update($data);

        return ApiResponse::success($this->settingsPayload($settings->fresh()), 'Settings saved');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Payment::query()->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $filename = 'payments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Student', 'Payer', 'Phone', 'Amount', 'Method', 'Status', 'Date', 'Reference']);
            foreach ($query->cursor() as $payment) {
                fputcsv($out, [
                    $payment->id,
                    $payment->student_name,
                    $payment->payer_name,
                    $payment->payer_phone,
                    $payment->amount,
                    $payment->payment_method,
                    $payment->status,
                    $payment->created_at?->toDateString(),
                    $payment->payment_reference,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function parentDashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->portal->parentFees($request->user()));
    }

    public function razorpayConfig(): JsonResponse
    {
        $settings = PaymentSetting::query()->first();

        return ApiResponse::success([
            'enabled' => (bool) ($settings?->enable_razorpay),
            'key_id' => $settings?->razorpay_key_id,
        ]);
    }

    public function createRazorpayOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'student_name' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = PaymentSetting::query()->first();
        if (! ($settings?->enable_razorpay) || ! $settings->razorpay_key_id || ! $settings->razorpay_webhook_secret) {
            return ApiResponse::error('Online payments are not configured. Set Razorpay key id and secret in Settings.', 422);
        }

        $user = $request->user();
        $tenant = Tenant::query()->first();
        $amountPaise = (int) round($data['amount'] * 100);
        $secret = $settings->razorpay_webhook_secret;

        try {
            $response = Http::withBasicAuth($settings->razorpay_key_id, $secret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'receipt' => 'fee_'.$user->id.'_'.time(),
                ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Could not reach Razorpay. Try again later.', 502);
        }

        if (! $response->successful() || ! $response->json('id')) {
            return ApiResponse::error('Razorpay order creation failed. Check key id/secret.', 422);
        }

        $orderId = (string) $response->json('id');

        $payment = Payment::create([
            'tenant_id' => $tenant?->id,
            'student_name' => $data['student_name'] ?? null,
            'payer_name' => $user->name,
            'payer_phone' => $user->phone,
            'amount' => $data['amount'],
            'payment_method' => 'razorpay',
            'payment_reference' => $orderId,
            'status' => 'pending',
        ]);

        return ApiResponse::success([
            'order_id' => $orderId,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'key_id' => $settings->razorpay_key_id,
            'payment_id' => $payment->id,
        ], 'Order created');
    }

    public function verifyRazorpay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'payment_id' => ['nullable', 'integer'],
        ]);

        $payment = Payment::query()
            ->when($data['payment_id'] ?? null, fn ($q, $id) => $q->where('id', $id))
            ->where('payment_reference', $data['razorpay_order_id'])
            ->first();

        if (! $payment) {
            return ApiResponse::error('Payment record not found', 404);
        }

        $settings = PaymentSetting::query()->first();
        $secret = $settings?->razorpay_webhook_secret;
        if (! $secret) {
            return ApiResponse::error('Razorpay secret is not configured.', 422);
        }

        $expected = hash_hmac(
            'sha256',
            $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'],
            $secret,
        );
        if (! hash_equals($expected, $data['razorpay_signature'])) {
            return ApiResponse::error('Invalid payment signature', 422);
        }

        $payment->update([
            'status' => 'verified',
            'payment_reference' => $data['razorpay_payment_id'],
            'verified_at' => now(),
            'verified_by_user_id' => $request->user()?->id,
        ]);

        $this->feePayments->applyPayment($payment->fresh());

        $this->notifications->notifyAdmins(
            SchoolNotificationService::EVENT_FEE_PAYMENT,
            'Fee payment received',
            sprintf('Online payment of ₹%s received from %s.', number_format((float) $payment->amount, 0), $payment->payer_name),
            ['payment_id' => $payment->id],
        );

        return ApiResponse::success($this->toRow($payment->fresh()), 'Payment verified');
    }

    public function outstanding(): JsonResponse
    {
        $pending = Payment::query()->where('status', 'pending')->get();
        $verifiedMonth = Payment::query()
            ->where('status', 'verified')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();

        return ApiResponse::success([
            'pending_count' => $pending->count(),
            'pending_amount' => (float) $pending->sum('amount'),
            'verified_this_month' => (float) $verifiedMonth->sum('amount'),
            'items' => $pending->take(20)->map(fn (Payment $p) => $this->toRow($p))->values(),
        ]);
    }

    public function studentSummary(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $payments = Payment::query()
            ->where('student_name', $student->full_name)
            ->orWhere('admission_number', $student->card_number)
            ->latest()
            ->get();

        $feeSummary = $this->feePayments->studentFeeSummary($student);

        return ApiResponse::success([
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'verified_total' => (float) $payments->where('status', 'verified')->sum('amount'),
            'pending_total' => (float) $payments->where('status', 'pending')->sum('amount'),
            'payment_count' => $payments->count(),
            'fee_total' => $feeSummary['fee_total'],
            'fee_paid' => $feeSummary['fee_paid'],
            'fee_balance' => $feeSummary['fee_balance'],
            'assigned_fees' => $feeSummary['fees']->map(fn (StudentFee $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'amount' => (float) $f->amount,
                'paid_amount' => (float) $f->paid_amount,
                'balance' => $f->balance(),
                'status' => $f->status,
                'due_date' => $f->due_date?->format('Y-m-d'),
            ])->values(),
        ]);
    }

    public function studentTimeline(IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $payments = Payment::query()
            ->where(function ($q) use ($student) {
                $q->where('student_name', $student->full_name)
                    ->orWhere('admission_number', $student->card_number);
            })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Payment $p) => $this->toRow($p));

        return ApiResponse::success($payments);
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status === 'refunded') {
            return ApiResponse::error('Payment is already refunded.', 422);
        }

        if ($payment->status !== 'verified') {
            return ApiResponse::error('Only verified payments can be refunded.', 422);
        }

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refunded_by_user_id' => $request->user()?->id,
            'remarks' => $data['remarks'] ?? $payment->remarks,
        ]);

        return ApiResponse::success($this->toRow($payment->fresh()), 'Payment refunded');
    }

    public function receipt(Payment $payment)
    {
        if (! in_array($payment->status, ['verified', 'refunded'], true)) {
            return ApiResponse::error('Receipt is available only for verified payments.', 422);
        }

        $school = config('app.name', 'Little Stars Kindergarten');
        $pdf = Pdf::loadView('receipts.payment', [
            'schoolName' => $school,
            'receiptNumber' => sprintf('RCP-%s-%05d', $payment->created_at?->format('Y') ?? date('Y'), $payment->id),
            'date' => $payment->created_at?->format('d M Y, h:i A'),
            'status' => $payment->status === 'refunded' ? 'Refunded' : 'Verified',
            'studentName' => $payment->student_name ?: '—',
            'admissionNumber' => $payment->admission_number ?: '—',
            'payerName' => $payment->payer_name,
            'payerPhone' => $payment->payer_phone ?: '—',
            'method' => $payment->payment_method,
            'reference' => $payment->payment_reference ?: '—',
            'remarks' => $payment->remarks,
            'amount' => number_format((float) $payment->amount, 2),
            'verifiedAt' => $payment->verified_at?->format('d M Y, h:i A'),
        ]);

        return $pdf->download('payment-receipt-'.$payment->id.'.pdf');
    }

    private function validatedPayment(Request $request): array
    {
        return $request->validate([
            'student_name' => ['nullable', 'string', 'max:120'],
            'admission_number' => ['nullable', 'string', 'max:40'],
            'payer_name' => ['required', 'string', 'max:120'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', Rule::in(Payment::METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'proof_path' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(Payment::STATUSES)],
        ]);
    }

    private function toRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'student_name' => $payment->student_name,
            'admission_number' => $payment->admission_number,
            'payer_name' => $payment->payer_name,
            'payer_phone' => $payment->payer_phone,
            'amount' => (float) $payment->amount,
            'amount_label' => '₹'.number_format((float) $payment->amount, 0),
            'payment_method' => $payment->payment_method,
            'method_label' => strtoupper($payment->payment_method),
            'payment_reference' => $payment->payment_reference,
            'status' => $payment->status,
            'status_label' => ucfirst($payment->status),
            'proof_path' => $payment->proof_path,
            'remarks' => $payment->remarks,
            'date' => $payment->created_at->format('d M Y'),
            'date_raw' => $payment->created_at->toDateString(),
            'verified_at' => $payment->verified_at?->format('d M Y, h:i A'),
            'refunded_at' => $payment->refunded_at?->format('d M Y, h:i A'),
        ];
    }

    private function settingsPayload(?PaymentSetting $settings = null): array
    {
        $settings ??= PaymentSetting::query()->first();
        $qrUrl = $settings?->upi_qr_path
            ? (str_starts_with($settings->upi_qr_path, 'http') ? $settings->upi_qr_path : url('/storage/'.ltrim($settings->upi_qr_path, '/')))
            : null;

        return [
            'upi_id' => $settings?->upi_id,
            'account_name' => $settings?->account_name,
            'account_number' => $settings?->account_number,
            'ifsc_code' => $settings?->ifsc_code,
            'bank_name' => $settings?->bank_name,
            'branch' => $settings?->branch,
            'upi_qr_path' => $settings?->upi_qr_path,
            'upi_qr_url' => $qrUrl,
            'enable_upi' => $settings?->enable_upi ?? true,
            'enable_cash' => $settings?->enable_cash ?? true,
            'enable_qr' => $settings?->enable_qr ?? true,
            'enable_razorpay' => $settings?->enable_razorpay ?? false,
            'razorpay_key_id' => $settings?->razorpay_key_id,
            'payment_note' => $settings?->payment_note,
            'methods' => collect(['upi', 'cash', 'qr', 'razorpay'])
                ->filter(fn ($m) => match ($m) {
                    'upi' => $settings?->enable_upi ?? true,
                    'cash' => $settings?->enable_cash ?? true,
                    'qr' => $settings?->enable_qr ?? true,
                    'razorpay' => $settings?->enable_razorpay ?? false,
                    default => false,
                })->values()->all(),
        ];
    }
}
