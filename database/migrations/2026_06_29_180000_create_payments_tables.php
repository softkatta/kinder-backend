<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('upi_id')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->string('upi_qr_path')->nullable();
            $table->boolean('enable_upi')->default(true);
            $table->boolean('enable_cash')->default(true);
            $table->boolean('enable_qr')->default(true);
            $table->boolean('enable_razorpay')->default(false);
            $table->string('razorpay_key_id')->nullable();
            $table->string('razorpay_webhook_secret')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_name')->nullable();
            $table->string('admission_number')->nullable();
            $table->string('payer_name');
            $table->string('payer_phone')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method'); // upi, cash, qr, razorpay
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('pending'); // pending, verified, rejected
            $table->string('proof_path')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'payment_method']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_settings');
    }
};
