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
        Schema::create('diklat', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('jenis_pelatihan_id')->constrained('jenis_pelatihan')->restrictOnDelete()->restrictOnUpdate();
            $table->string('nomor_surat');
            $table->string('materi_pengembangan');
            $table->date('dari_tanggal_pelaksanaan');
            $table->date('sampai_tanggal_pelaksanaan');
            $table->integer('jumlah_jam_pelatihan');
            $table->string('berkas_path')->nullable();
            $table->string('berkas_filename')->nullable();
            $table->timestampsWithAudit();

            $table->index('uuid', 'diklat_uuid_hash_index', 'hash');
        });

        DB::statement('create index diklat_pegawai_tanggal_index on diklat (pegawai_id, dari_tanggal_pelaksanaan) where deleted_at is null');
        DB::statement('create index diklat_jenis_pelatihan_index on diklat (jenis_pelatihan_id) where deleted_at is null');
        DB::statement('create index diklat_nomor_surat_prefix_index on diklat (lower(nomor_surat) text_pattern_ops) where deleted_at is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diklat');
    }
};
