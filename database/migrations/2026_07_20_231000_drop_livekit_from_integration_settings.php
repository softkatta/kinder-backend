<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_settings')) {
            return;
        }

        Schema::table('integration_settings', function (Blueprint $table) {
            $columns = [];
            foreach (['livekit_enabled', 'livekit_url', 'livekit_api_key', 'livekit_api_secret'] as $column) {
                if (Schema::hasColumn('integration_settings', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_settings')) {
            return;
        }

        Schema::table('integration_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_settings', 'livekit_enabled')) {
                $table->boolean('livekit_enabled')->default(false)->after('broadcast_scheme');
            }
            if (! Schema::hasColumn('integration_settings', 'livekit_url')) {
                $table->string('livekit_url')->nullable()->after('livekit_enabled');
            }
            if (! Schema::hasColumn('integration_settings', 'livekit_api_key')) {
                $table->string('livekit_api_key')->nullable()->after('livekit_url');
            }
            if (! Schema::hasColumn('integration_settings', 'livekit_api_secret')) {
                $table->text('livekit_api_secret')->nullable()->after('livekit_api_key');
            }
        });
    }
};
