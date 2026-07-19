<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('scan_code', 32)->nullable()->unique()->after('qr_token');
        });

        Schema::table('id_cards', function (Blueprint $table) {
            $table->string('scan_code', 32)->nullable()->unique()->after('qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('scan_code');
        });

        Schema::table('id_cards', function (Blueprint $table) {
            $table->dropColumn('scan_code');
        });
    }
};
