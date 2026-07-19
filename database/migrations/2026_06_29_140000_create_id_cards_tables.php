<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('card_type', 20); // student, teacher, staff, parent, guest
            $table->string('card_number', 40)->unique();
            $table->string('qr_token', 64)->unique();
            $table->string('status', 20)->default('active'); // active, inactive, blocked, expired
            $table->string('full_name');
            $table->string('photo_path')->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->string('emergency_contact')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'card_type', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('id_card_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('present'); // present, late, absent, half_day, leave
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('method', 20)->default('qr'); // qr, manual
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['id_card_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('id_card_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('id_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('qr_token', 64);
            $table->string('result', 30); // success, invalid, expired, blocked, duplicate, not_student
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['id_card_id', 'created_at']);
            $table->index(['qr_token', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_scan_logs');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('id_cards');
    }
};
