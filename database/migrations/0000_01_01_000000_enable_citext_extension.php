<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('citext');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop extension if exists citext');
    }
};
