<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\Fees\StudentFeePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function __construct(
        private readonly StudentFeePaymentService $feePayments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $settings = PaymentSetting::query()->first();
        $secret = $settings?->razorpay_webhook_secret;

        if ($secret && $signature !== '') {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expected, $signature)) {
                return ApiResponse::error('Invalid signature', 400);
            }
        }

        $data = json_decode($payload, true);
        $event = $data['event'] ?? null;

        if ($event === 'payment.captured') {
            $entity = $data['payload']['payment']['entity'] ?? [];
            $orderId = $entity['order_id'] ?? null;
            $paymentId = $entity['id'] ?? null;

            if ($orderId && $paymentId) {
                $payment = Payment::query()->where('payment_reference', $orderId)->first();
                if ($payment && $payment->status === 'pending') {
                    $payment->update([
                        'status' => 'verified',
                        'payment_reference' => $paymentId,
                        'verified_at' => now(),
                    ]);
                    $this->feePayments->applyPayment($payment->fresh());
                }
            }
        }

        Log::info('Razorpay webhook received', ['event' => $event]);

        return ApiResponse::success(null, 'OK');
    }
}
