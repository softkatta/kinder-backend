<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 20);
            $table->string('label', 80)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('exam_type', 30)->default('term');
            $table->string('class_name', 40);
            $table->string('subject', 80)->nullable();
            $table->date('exam_date')->nullable();
            $table->unsignedSmallInteger('max_marks')->default(100);
            $table->string('status', 20)->default('scheduled');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'academic_year_id', 'status']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('student_name', 120);
            $table->string('roll_number', 40)->nullable();
            $table->string('class_name', 40);
            $table->decimal('marks_obtained', 6, 2)->default(0);
            $table->string('grade', 10)->nullable();
            $table->string('result_status', 20)->default('pass');
            $table->text('remarks')->nullable();
            $table->timestamp('marksheet_printed_at')->nullable();
            $table->timestamp('certificate_printed_at')->nullable();
            $table->timestamps();
            $table->index(['exam_id', 'student_name']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_code', 32)->unique();
            $table->string('qr_token', 64)->unique();
            $table->string('full_name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('event_name', 160);
            $table->date('event_date')->nullable();
            $table->string('event_location', 200)->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'event_date']);
        });

        Schema::create('guest_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('relation', 60)->nullable();
            $table->boolean('can_entry')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guest_entry_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_companion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('person_name', 120);
            $table->string('direction', 10);
            $table->timestamp('scanned_at');
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->index(['guest_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_entry_logs');
        Schema::dropIfExists('guest_companions');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('academic_years');
    }
};
