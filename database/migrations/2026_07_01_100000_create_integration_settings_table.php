<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            $table->boolean('email_enabled')->default(false);
            $table->string('email_mailer', 40)->default('smtp');
            $table->string('email_host')->nullable();
            $table->unsignedSmallInteger('email_port')->nullable();
            $table->string('email_username')->nullable();
            $table->text('email_password')->nullable();
            $table->string('email_encryption', 10)->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_from_name')->nullable();

            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider', 20)->default('twilio');
            $table->string('whatsapp_account_sid')->nullable();
            $table->text('whatsapp_auth_token')->nullable();
            $table->string('whatsapp_from_number')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();

            $table->boolean('broadcast_enabled')->default(true);
            $table->string('broadcast_driver', 20)->default('reverb');
            $table->string('broadcast_app_id')->nullable();
            $table->string('broadcast_key')->nullable();
            $table->text('broadcast_secret')->nullable();
            $table->string('broadcast_cluster', 40)->nullable();
            $table->string('broadcast_host')->nullable();
            $table->unsignedSmallInteger('broadcast_port')->nullable();
            $table->string('broadcast_scheme', 10)->default('http');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
