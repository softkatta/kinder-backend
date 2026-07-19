<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('cms_item_id')->nullable()->constrained('cms_items')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, scheduled, live, paused, stopped
            $table->unsignedBigInteger('active_camera_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('live_stream_cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('stream_type', 20)->default('hls'); // hls, youtube, vimeo, embed
            $table->text('stream_url');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['live_stream_id', 'display_order']);
        });

        Schema::table('live_streams', function (Blueprint $table) {
            $table->foreign('active_camera_id')
                ->references('id')
                ->on('live_stream_cameras')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropForeign(['active_camera_id']);
        });
        Schema::dropIfExists('live_stream_cameras');
        Schema::dropIfExists('live_streams');
    }
};
