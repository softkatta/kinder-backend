<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('applicant_name');
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('grade_level', 20)->nullable();
            $table->json('parent_info')->nullable();
            $table->json('address_info')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
