<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The database conventions are PostgreSQL specific.');
    }
});

it('stores every timestamp column with a time zone', function () {
    $offending = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('data_type', 'timestamp without time zone')
        ->get(['table_name', 'column_name']);

    expect($offending)->toBeEmpty();
});

it('exposes the uuid column with a hash index and no unique constraint', function () {
    $uuidType = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->where('column_name', 'uuid')
        ->value('data_type');

    expect($uuidType)->toBe('uuid');

    $index = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'users')
        ->where('indexname', 'users_uuid_hash_index')
        ->first();

    expect($index)->not->toBeNull()
        ->and(str_contains(strtolower($index->indexdef), 'using hash'))->toBeTrue()
        ->and(str_contains($index->indexdef, 'UNIQUE'))->toBeFalse();
});

it('indexes the soft delete column', function () {
    $index = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'users')
        ->where('indexdef', 'like', '%(deleted_at)%')
        ->first();

    expect($index)->not->toBeNull();
});

it('keeps the audit columns the same type as the primary key without foreign keys', function () {
    $columns = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->whereIn('column_name', ['id', 'created_by', 'updated_by', 'deleted_by'])
        ->pluck('data_type', 'column_name')
        ->all();

    expect($columns)->toMatchArray([
        'id' => 'bigint',
        'created_by' => 'bigint',
        'updated_by' => 'bigint',
        'deleted_by' => 'bigint',
    ]);

    $foreignKeys = DB::table('information_schema.table_constraints')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->where('constraint_type', 'FOREIGN KEY')
        ->pluck('constraint_name')
        ->all();

    expect($foreignKeys)->toBeEmpty();
});

it('keeps the unique email constraint partial to active users', function () {
    $index = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'users')
        ->where('indexname', 'users_email_unique')
        ->first();

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});
