<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_streams')) {
            return;
        }

        Schema::table('live_streams', function (Blueprint $table) {
            if (! Schema::hasColumn('live_streams', 'layout_mode')) {
                $table->unsignedTinyInteger('layout_mode')->default(1)->after('active_camera_id');
            }
            if (! Schema::hasColumn('live_streams', 'active_camera_ids')) {
                $table->json('active_camera_ids')->nullable()->after('layout_mode');
            }
        });

        foreach (DB::table('live_streams')->whereNotNull('active_camera_id')->whereNull('active_camera_ids')->get() as $row) {
            DB::table('live_streams')->where('id', $row->id)->update([
                'active_camera_ids' => json_encode([(int) $row->active_camera_id]),
                'layout_mode' => 1,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('live_streams')) {
            return;
        }

        Schema::table('live_streams', function (Blueprint $table) {
            if (Schema::hasColumn('live_streams', 'active_camera_ids')) {
                $table->dropColumn('active_camera_ids');
            }
            if (Schema::hasColumn('live_streams', 'layout_mode')) {
                $table->dropColumn('layout_mode');
            }
        });
    }
};
