<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('template_assets');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('template_categories');
        Schema::dropIfExists('template_variables');
        Schema::dropIfExists('template_designer_settings');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }

    public function down(): void
    {
        // Module removed — tables are not restored on rollback.
    }
};
