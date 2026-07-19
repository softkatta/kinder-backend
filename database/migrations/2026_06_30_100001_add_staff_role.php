<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('name', 'staff')->exists();
        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'staff',
                'label' => 'Staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'staff')->delete();
    }
};
