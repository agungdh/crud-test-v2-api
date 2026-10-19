<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jenis_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('jenis_pelatihan');
            $table->timestampsWithAudit();

            $table->index('uuid', 'jenis_pelatihan_uuid_hash_index', 'hash');
        });

        DB::statement('create unique index jenis_pelatihan_nama_unique on jenis_pelatihan (lower(jenis_pelatihan)) where deleted_at is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_pelatihan');
    }
};
