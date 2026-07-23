<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_settings', 'razorpay_key_secret')) {
            Schema::table('payment_settings', function (Blueprint $table) {
                $table->string('razorpay_key_secret')->nullable()->after('razorpay_key_id');
            });
        }

        // Prior installs stored the Key Secret in webhook_secret — copy once for continuity.
        if (Schema::hasColumn('payment_settings', 'razorpay_webhook_secret')) {
            DB::table('payment_settings')
                ->whereNull('razorpay_key_secret')
                ->whereNotNull('razorpay_webhook_secret')
                ->update([
                    'razorpay_key_secret' => DB::raw('razorpay_webhook_secret'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn('razorpay_key_secret');
        });
    }
};
