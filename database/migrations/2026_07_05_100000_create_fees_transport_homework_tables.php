<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('frequency', 20)->default('yearly'); // monthly, quarterly, yearly, one_time
            $table->string('grade_level', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('id_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending, partial, paid, waived
            $table->string('academic_year', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['id_card_id', 'status']);
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('pickup_points')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::table('id_cards', function (Blueprint $table) {
            $table->foreignId('transport_route_id')->nullable()->after('user_id')->constrained('transport_routes')->nullOnDelete();
        });

        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('class_name', 40)->nullable();
            $table->date('due_date')->nullable();
            $table->string('emoji', 8)->default('📚');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->foreignId('id_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('submitted'); // submitted, reviewed, returned
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'id_card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homeworks');
        Schema::table('id_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transport_route_id');
        });
        Schema::dropIfExists('transport_routes');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_categories');
    }
};
