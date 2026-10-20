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
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('nip', 30);
            $table->string('nama');
            $table->date('tanggal_lahir')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('bidang')->nullable();
            $table->boolean('is_male')->nullable();
            $table->date('tmt')->nullable();
            $table->timestampsWithAudit();

            $table->index('uuid', 'pegawai_uuid_hash_index', 'hash');
        });

        DB::statement('create unique index pegawai_user_id_unique on pegawai (user_id) where deleted_at is null');
        DB::statement('create unique index pegawai_nip_unique on pegawai (nip) where deleted_at is null');
        DB::statement('create index pegawai_nama_lower_index on pegawai (lower(nama) text_pattern_ops) where deleted_at is null');
        DB::statement('create index pegawai_nip_prefix_index on pegawai (nip varchar_pattern_ops) where deleted_at is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
