<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained('template_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('paper_size', 32)->default('a4_portrait');
            $table->decimal('custom_width_mm', 8, 2)->nullable();
            $table->decimal('custom_height_mm', 8, 2)->nullable();
            $table->string('orientation', 16)->default('portrait');
            $table->string('background_image')->nullable();
            $table->string('background_color', 16)->default('#ffffff');
            $table->json('border_config')->nullable();
            $table->json('watermark_config')->nullable();
            $table->json('canvas_json');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'category_id', 'is_active']);
            $table->index(['category_id', 'is_default']);
        });

        Schema::create('template_variables', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('group', 32)->default('custom');
            $table->string('data_type', 24)->default('string');
            $table->string('resolver')->nullable();
            $table->text('sample_value')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'is_active']);
        });

        Schema::create('template_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type', 64)->nullable();
            $table->string('asset_type', 24)->default('image');
            $table->json('meta')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'template_id']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->constrained('templates')->restrictOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('verification_token', 64)->unique();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('student_id')->nullable()->constrained('id_cards')->nullOnDelete();
            $table->string('class_name')->nullable();
            $table->string('section')->nullable();
            $table->string('title')->nullable();
            $table->string('file_path')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->string('status', 16)->default('valid');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'template_id', 'generated_at']);
            $table->index(['student_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('template_designer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_designer_settings');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('template_assets');
        Schema::dropIfExists('template_variables');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('template_categories');
    }
};
