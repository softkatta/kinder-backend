<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('document_templates');
    }

    public function down(): void
    {
        // Design feature removed — no rollback.
    }
};
