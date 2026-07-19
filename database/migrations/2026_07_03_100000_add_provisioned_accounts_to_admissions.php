<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->foreignId('student_id_card_id')->nullable()->after('reviewed_at')->constrained('id_cards')->nullOnDelete();
            $table->foreignId('parent_user_id')->nullable()->after('student_id_card_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id_card_id');
            $table->dropConstrainedForeignId('parent_user_id');
        });
    }
};
