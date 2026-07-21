<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_stream_cameras', function (Blueprint $table) {
            if (! Schema::hasColumn('live_stream_cameras', 'audio_volume')) {
                $table->unsignedTinyInteger('audio_volume')->default(100)->after('audio_muted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_stream_cameras', function (Blueprint $table) {
            if (Schema::hasColumn('live_stream_cameras', 'audio_volume')) {
                $table->dropColumn('audio_volume');
            }
        });
    }
};
