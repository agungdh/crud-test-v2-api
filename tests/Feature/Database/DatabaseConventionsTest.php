<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The database conventions are PostgreSQL specific.');
    }
});

it('provides the timestamps with audit blueprint macro', function () {
    expect(Blueprint::hasMacro('timestampsWithAudit'))->toBeTrue();
});

it('stores every timestamp column with a time zone', function () {
    $offending = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('data_type', 'timestamp without time zone')
        ->get(['table_name', 'column_name']);

    expect($offending)->toBeEmpty();
});

it('restricts deleting and updating referenced rows for every foreign key', function () {
    $constraints = DB::table('information_schema.referential_constraints')
        ->where('constraint_schema', 'public')
        ->get(['constraint_name', 'delete_rule', 'update_rule']);

    expect($constraints)->not->toBeEmpty();

    $offending = $constraints
        ->reject(fn (object $constraint) => $constraint->delete_rule === 'RESTRICT' && $constraint->update_rule === 'RESTRICT')
        ->pluck('constraint_name');

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
        ->and($index->indexdef)->toContain('lower(')
        ->and($index->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});

it('keeps the unique username constraint partial to active users', function () {
    $index = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->where('tablename', 'users')
        ->where('indexname', 'users_username_unique')
        ->first();

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('lower(')
        ->and($index->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});

it('stores email and username as varchar', function () {
    $columns = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->whereIn('column_name', ['email', 'username'])
        ->pluck('udt_name', 'column_name')
        ->all();

    expect($columns)->toMatchArray([
        'email' => 'varchar',
        'username' => 'varchar',
    ]);
});

it('allows users without an email or username', function () {
    $columns = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'users')
        ->whereIn('column_name', ['email', 'username'])
        ->pluck('is_nullable', 'column_name')
        ->all();

    expect($columns)->toMatchArray([
        'email' => 'YES',
        'username' => 'YES',
    ]);
});

it('enforces case-insensitive uniqueness', function (string $column, string $first, string $second) {
    User::factory()->create([$column => $first]);

    expect(fn () => User::factory()->create([$column => $second]))
        ->toThrow(QueryException::class);
})->with([
    'email' => ['email', 'User@Example.com', 'user@example.com'],
    'username' => ['username', 'Budi', 'budi'],
]);
