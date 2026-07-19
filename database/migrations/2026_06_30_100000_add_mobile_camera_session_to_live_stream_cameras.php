<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_stream_cameras', function (Blueprint $table) {
            $table->foreignId('publisher_user_id')->nullable()->after('live_stream_id')->constrained('users')->nullOnDelete();
            $table->string('connection_status', 20)->default('offline')->after('is_enabled');
            $table->string('device_name')->nullable()->after('connection_status');
            $table->unsignedTinyInteger('battery_level')->nullable()->after('device_name');
            $table->unsignedTinyInteger('signal_strength')->nullable()->after('battery_level');
            $table->boolean('audio_muted')->default(false)->after('signal_strength');
            $table->timestamp('joined_at')->nullable()->after('audio_muted');
            $table->timestamp('last_seen_at')->nullable()->after('joined_at');

            $table->index(['live_stream_id', 'publisher_user_id']);
            $table->index(['publisher_user_id', 'connection_status']);
        });
    }

    public function down(): void
    {
        Schema::table('live_stream_cameras', function (Blueprint $table) {
            $table->dropForeign(['publisher_user_id']);
            $table->dropIndex(['live_stream_id', 'publisher_user_id']);
            $table->dropIndex(['publisher_user_id', 'connection_status']);
            $table->dropColumn([
                'publisher_user_id',
                'connection_status',
                'device_name',
                'battery_level',
                'signal_strength',
                'audio_muted',
                'joined_at',
                'last_seen_at',
            ]);
        });
    }
};
