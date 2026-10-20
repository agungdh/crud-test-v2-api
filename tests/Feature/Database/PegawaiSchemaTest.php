<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The pegawai schema conventions are PostgreSQL specific.');
    }
});

function pegawaiIndex(string $name): ?object
{
    return DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'pegawai')
        ->where('indexname', $name)
        ->first();
}

it('exposes the uuid column with a hash index and no unique constraint', function () {
    $uuidType = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'pegawai')
        ->where('column_name', 'uuid')
        ->value('data_type');

    expect($uuidType)->toBe('uuid');

    $index = pegawaiIndex('pegawai_uuid_hash_index');

    expect($index)->not->toBeNull()
        ->and(str_contains(strtolower($index->indexdef), 'using hash'))->toBeTrue()
        ->and(str_contains($index->indexdef, 'UNIQUE'))->toBeFalse();
});

it('keeps user_id and nip unique only for active rows', function (string $indexName) {
    $index = pegawaiIndex($indexName);

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('WHERE (deleted_at IS NULL)');
})->with([
    'user_id' => ['pegawai_user_id_unique'],
    'nip' => ['pegawai_nip_unique'],
]);

it('indexes the lowercased nama for prefix search', function () {
    $index = pegawaiIndex('pegawai_nama_lower_index');

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('lower(')
        ->and($index->indexdef)->toContain('text_pattern_ops')
        ->and($index->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});

it('indexes the soft delete column', function () {
    $index = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'pegawai')
        ->where('indexdef', 'like', '%(deleted_at)%')
        ->first();

    expect($index)->not->toBeNull();
});

it('restricts deleting and updating a user that still has a pegawai', function () {
    $rules = DB::table('information_schema.referential_constraints')
        ->where('constraint_schema', 'public')
        ->where('constraint_name', 'pegawai_user_id_foreign')
        ->first(['delete_rule', 'update_rule']);

    expect($rules->delete_rule)->toBe('RESTRICT')
        ->and($rules->update_rule)->toBe('RESTRICT');
});

it('removes the name column from users', function () {
    $exists = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->where('column_name', 'name')
        ->exists();

    expect($exists)->toBeFalse();
});

it('stores the date and boolean columns with the right types', function () {
    $columns = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'pegawai')
        ->whereIn('column_name', ['tanggal_lahir', 'tmt', 'is_male'])
        ->pluck('data_type', 'column_name')
        ->all();

    expect($columns)->toMatchArray([
        'tanggal_lahir' => 'date',
        'tmt' => 'date',
        'is_male' => 'boolean',
    ]);
});
