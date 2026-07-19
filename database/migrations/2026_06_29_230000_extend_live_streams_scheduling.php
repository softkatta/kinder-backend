<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('mode', 20)->default('instant')->after('cms_item_id');
            $table->string('banner')->nullable()->after('description');
            $table->date('event_date')->nullable()->after('banner');
            $table->timestamp('scheduled_start_at')->nullable()->after('event_date');
            $table->timestamp('scheduled_end_at')->nullable()->after('scheduled_start_at');
            $table->string('stream_source', 30)->nullable()->after('scheduled_end_at');
            $table->boolean('enable_countdown')->default(true)->after('stream_source');
            $table->boolean('enable_reminder')->default(true)->after('enable_countdown');
            $table->json('notify_before_minutes')->nullable()->after('enable_reminder');
            $table->string('visibility', 20)->default('public')->after('notify_before_minutes');
            $table->boolean('auto_start')->default(true)->after('visibility');
            $table->boolean('auto_end')->default(true)->after('auto_start');
            $table->json('notifications_sent')->nullable()->after('auto_end');
            $table->unsignedInteger('viewer_count')->default(0)->after('notifications_sent');
            $table->timestamp('cancelled_at')->nullable()->after('stopped_at');
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');

        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn([
                'mode', 'banner', 'event_date', 'scheduled_start_at', 'scheduled_end_at',
                'stream_source', 'enable_countdown', 'enable_reminder', 'notify_before_minutes',
                'visibility', 'auto_start', 'auto_end', 'notifications_sent', 'viewer_count', 'cancelled_at',
            ]);
        });
    }
};
