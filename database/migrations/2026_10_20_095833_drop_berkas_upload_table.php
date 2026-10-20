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
        Schema::dropIfExists('berkas_upload');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('berkas_upload', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->string('object_key');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestampTz('upload_url_expires_at');
            $table->timestampTz('purge_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsWithAudit();

            $table->index('uuid', 'berkas_upload_uuid_hash_index', 'hash');
        });

        DB::statement('create unique index berkas_upload_object_key_unique on berkas_upload (object_key) where deleted_at is null');
        DB::statement('create index berkas_upload_purge_index on berkas_upload (purge_at) where deleted_at is null and consumed_at is null');
    }
};
